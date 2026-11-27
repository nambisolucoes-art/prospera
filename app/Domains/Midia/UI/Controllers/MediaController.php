<?php

declare(strict_types=1);

namespace App\Domains\Midia\UI\Controllers;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Application\Context\OrganizationContext;
use App\Domains\Institucional\Application\Queries\Profile\ListProfileOwnersQuery;
use App\Domains\Midia\Application\Actions\DeleteMediaAction;
use App\Domains\Midia\Application\Actions\UpdateMediaAction;
use App\Domains\Midia\Application\Actions\UploadMediaAction;
use App\Domains\Midia\Application\Data\ListMediaData;
use App\Domains\Midia\Application\Data\UpdateMediaData;
use App\Domains\Midia\Application\Data\UploadMediaData;
use App\Domains\Midia\Application\Queries\FindMediaQuery;
use App\Domains\Midia\Application\Queries\ListMediaQuery;
use App\Domains\Midia\Domain\Contracts\ImageInspector;
use App\Domains\Midia\Domain\Contracts\MediaStorage;
use App\Domains\Midia\Domain\Enums\MediaPurpose;
use App\Domains\Midia\Domain\Models\MediaAsset;
use App\Domains\Midia\Domain\ValueObjects\MediaOwner;
use App\Support\ApiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Biblioteca de mídia de uma empresa ou de uma unidade (S08). O dono vem da URL e é validado pelo contexto organizacional.
 * Nenhum caminho de armazenamento sai daqui: os arquivos só são entregues por rotas autenticadas e autorizadas, com o
 * tipo vindo do banco e nunca do cliente. A mídia de outro escopo não existe para quem pede (404).
 */
final class MediaController
{
    /** Entrada da biblioteca: as empresas e unidades cuja mídia o acesso alcança, sem escolha prévia. */
    public function owners(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        ListProfileOwnersQuery $listOwners,
    ): JsonResponse|View {
        $nodes = $permissions->nodesFor($request->user(), 'account.media.list');
        abort_if($nodes->isEmpty(), 403);

        return ApiPage::respond(['owners' => $listOwners->execute($account->requireId(), $nodes)]);
    }

    public function index(
        Request $request,
        OrganizationContext $context,
        ListMediaQuery $list,
        ImageInspector $inspector,
    ): JsonResponse|View {
        $owner = $this->owner($context);
        Gate::authorize('account.media.list', $owner->node());

        $filters = ListMediaData::fromInput($request->query());
        $page = $list->execute($owner, $filters, $request->url());

        return ApiPage::respond([
            'owner' => $this->ownerPayload($context, $owner),
            'media' => $page->through(fn (MediaAsset $media): array => $this->item($media)),
            'filters' => $filters->toArray(),
            'purposes' => MediaPurpose::options(),
            'canCreate' => Gate::allows('account.media.create', $owner->node()),
            'limits' => [
                'max_mb' => intdiv((int) config('media.limits.max_bytes'), 1048576),
                'max_megapixels' => $inspector->effectiveMegapixels(),
                'nominal_megapixels' => (int) config('media.limits.max_megapixels'),
                'types' => ['JPEG', 'PNG', 'WebP'],
            ],
        ]);
    }

    public function show(
        OrganizationContext $context,
        FindMediaQuery $find,
        string $midia,
    ): JsonResponse {
        $owner = $this->owner($context);
        Gate::authorize('account.media.list', $owner->node());

        $media = $this->found($find, $owner, $midia);

        return response()->json(['media' => $this->item($media)], headers: ['Cache-Control' => 'private, no-store']);
    }

    public function store(
        Request $request,
        OrganizationContext $context,
        UploadMediaAction $action,
    ): Response {
        $owner = $this->owner($context);
        Gate::authorize('account.media.list', $owner->node());

        $data = UploadMediaData::fromInput($request->all(), $request->file('file'));
        $action->execute($data, $owner);
        ApiPage::toast('Imagem enviada e otimizada. Ela fica na biblioteca e não é publicada.');

        return response()->noContent();
    }

    public function update(
        Request $request,
        OrganizationContext $context,
        FindMediaQuery $find,
        UpdateMediaAction $action,
        string $midia,
    ): Response {
        $owner = $this->owner($context);
        Gate::authorize('account.media.list', $owner->node());

        $media = $this->found($find, $owner, $midia);

        $action->execute(UpdateMediaData::fromInput($request->all()), $media);
        ApiPage::toast('Dados da imagem salvos.');

        return response()->noContent();
    }

    public function destroy(
        OrganizationContext $context,
        FindMediaQuery $find,
        DeleteMediaAction $action,
        string $midia,
    ): Response {
        $owner = $this->owner($context);
        Gate::authorize('account.media.list', $owner->node());

        $media = $this->found($find, $owner, $midia);

        $removed = $action->execute($media);
        ApiPage::toast($removed
            ? 'Imagem excluída.'
            : 'A imagem está em uso. A exclusão foi solicitada e acontece quando ela deixar de ser usada.');

        return response()->noContent();
    }

    /** Pré-visualização autorizada de um derivado. Não é uma URL pública. */
    public function variant(
        OrganizationContext $context,
        FindMediaQuery $find,
        MediaStorage $storage,
        string $midia,
        string $variante,
    ): StreamedResponse {
        $owner = $this->owner($context);
        Gate::authorize('account.media.list', $owner->node());

        $media = $this->found($find, $owner, $midia);
        $variant = $media->variants->firstWhere('kind', $variante);
        abort_if($variant === null, 404);

        $stream = $storage->readVariant($variant->public_path);
        abort_if($stream === null, 404);

        return $this->stream($stream, $variant->mime, 'inline', 'midia-'.$media->id.'-'.$variant->kind);
    }

    /** Download do original (pode conter EXIF e GPS): só quem edita a mídia, e como anexo. */
    public function original(
        OrganizationContext $context,
        FindMediaQuery $find,
        MediaStorage $storage,
        string $midia,
    ): StreamedResponse {
        $owner = $this->owner($context);
        Gate::authorize('account.media.list', $owner->node());

        $media = $this->found($find, $owner, $midia);
        Gate::authorize('account.media.update', $media->owner()->node());

        $stream = $storage->readOriginal($media->original_path);
        abort_if($stream === null, 404);

        return $this->stream($stream, $media->mime, 'attachment', 'midia-'.$media->id.'-original');
    }

    private function found(FindMediaQuery $find, MediaOwner $owner, string $id): MediaAsset
    {
        abort_unless(ctype_digit($id), 404);

        $media = $find->execute($owner, (int) $id);
        abort_if($media === null, 404);

        return $media;
    }

    /** @param resource $stream */
    private function stream($stream, string $mime, string $disposition, string $name): StreamedResponse
    {
        $extension = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition.'; filename="'.$name.'.'.$extension.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    /** @return array<string,mixed> */
    private function item(MediaAsset $media): array
    {
        $variants = $media->variants->sortBy('width')->values();
        $node = $media->owner()->node();

        return [
            'id' => $media->id,
            'purpose' => $media->purpose,
            'purpose_label' => $media->purpose()->label(),
            'scope' => $media->establishment_id === null ? 'corporate' : 'local',
            'establishment_name' => $media->establishment_id === null ? null : $media->getAttribute('establishment_name'),
            'mime' => $media->mime,
            'bytes' => $media->bytes,
            'width' => $media->width,
            'height' => $media->height,
            'status' => $media->status,
            'alt_text' => $media->alt_text,
            'license_note' => $media->license_note,
            'created_at' => $media->created_at?->toIso8601String(),
            'thumb_kind' => $variants->first()?->kind,
            'variants' => $variants->map(fn ($variant): array => [
                'kind' => $variant->kind,
                'width' => $variant->width,
                'height' => $variant->height,
                'bytes' => $variant->bytes,
                'mime' => $variant->mime,
            ])->all(),
            'can_update' => Gate::allows('account.media.update', $node),
            'can_delete' => Gate::allows('account.media.delete', $node),
        ];
    }

    /** @return array<string,mixed> */
    private function ownerPayload(OrganizationContext $context, MediaOwner $owner): array
    {
        if (! $owner->isEstablishment()) {
            $company = $context->company();

            return ['type' => 'company', 'code' => (string) $company?->code, 'name' => (string) $company?->name];
        }

        $establishment = $context->establishment();

        return [
            'type' => 'establishment',
            'code' => (string) $establishment?->code,
            'name' => (string) $establishment?->name,
            'company_name' => (string) $establishment?->company->name,
        ];
    }

    private function owner(OrganizationContext $context): MediaOwner
    {
        $establishment = $context->establishment();

        if ($establishment !== null) {
            return MediaOwner::establishment($context->accountId(), $establishment->company->id, $establishment->id);
        }

        $company = $context->company();
        abort_if($company === null, 404);

        return MediaOwner::company($context->accountId(), $company->id);
    }
}
