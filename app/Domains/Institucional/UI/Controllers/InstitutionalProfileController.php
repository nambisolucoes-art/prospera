<?php

declare(strict_types=1);

namespace App\Domains\Institucional\UI\Controllers;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Application\Context\OrganizationContext;
use App\Domains\Institucional\Application\Actions\Profile\PublishProfileSectionsAction;
use App\Domains\Institucional\Application\Actions\Profile\SaveProfileSectionAction;
use App\Domains\Institucional\Application\Actions\Profile\SetPublicAddressAction;
use App\Domains\Institucional\Application\Context\ProfileResolver;
use App\Domains\Institucional\Application\Data\Profile\PublishProfileSectionsData;
use App\Domains\Institucional\Application\Data\Profile\SaveProfileSectionData;
use App\Domains\Institucional\Application\Data\Profile\SetPublicAddressData;
use App\Domains\Institucional\Application\Queries\Profile\ListProfileOwnersQuery;
use App\Domains\Institucional\Application\Queries\Profile\ProfileImpactQuery;
use App\Domains\Institucional\Application\Queries\Profile\ShowProfileOwnerQuery;
use App\Domains\Institucional\Domain\Enums\ProfileSectionType;
use App\Domains\Institucional\Domain\Enums\ProfileState;
use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;
use App\Support\ApiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Perfil institucional de uma empresa ou de um estabelecimento (S06). O dono vem da URL e é validado pelo contexto
 * organizacional. Os valores efetivos saem sempre do `ProfileResolver`, e cada escrita confere o nó do dono.
 */
final class InstitutionalProfileController
{
    public function index(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        ListProfileOwnersQuery $listOwners,
    ): JsonResponse|View {
        $nodes = $permissions->nodesFor($request->user(), 'account.institutional.view');
        abort_if($nodes->isEmpty(), 403);

        return ApiPage::respond([
            'owners' => $listOwners->execute($account->requireId(), $nodes),
        ]);
    }

    public function show(
        Request $request,
        OrganizationContext $context,
        EffectivePermissions $permissions,
        ProfileResolver $resolver,
        ShowProfileOwnerQuery $showOwner,
        ListProfileOwnersQuery $listOwners,
    ): JsonResponse|View {
        $owner = $this->owner($context);
        Gate::authorize('account.institutional.view', $owner->node());

        $details = $showOwner->execute($owner);
        abort_if($details === null, 404);

        $draft = $resolver->resolveAll($owner, ProfileState::Draft);
        $published = $resolver->resolveAll($owner, ProfileState::Published);

        $sections = [];

        foreach (ProfileSectionType::cases() as $type) {
            // A galeria é local: só existe no estabelecimento.
            if ($type->isEstablishmentOnly() && ! $owner->isEstablishment()) {
                continue;
            }

            $sections[] = [
                'key' => $type->value,
                'label' => $type->label(),
                'kind' => $type->isOperational() ? 'operational' : 'editorial',
                'available' => $type->isAvailable(),
                'unavailable_reason' => $type->unavailableReason(),
                'can_disable' => $type->canDisable(),
                'establishment_only' => $type->isEstablishmentOnly(),
                'draft' => $draft[$type->value]->toArray(),
                'published' => $published[$type->value]->toArray(),
                'pending' => ! $type->isOperational() && $draft[$type->value]->toArray() !== $published[$type->value]->toArray(),
                'empty_value' => $type->emptyValue(),
            ];
        }

        $nodes = $permissions->nodesFor($request->user(), 'account.institutional.view');

        return ApiPage::respond([
            'owner' => $details,
            'canUpdate' => Gate::allows('account.institutional.update', $owner->node()),
            'canPublish' => Gate::allows('account.institutional.publish', $owner->node()),
            'sections' => $sections,
            'fonts' => $this->options(config('institutional.fonts')),
            'networks' => $this->options(array_map(
                fn (array $network): string => $network['label'],
                config('institutional.social_networks'),
            )),
            'units' => $owner->isEstablishment()
                ? []
                : ($listOwners->execute($owner->accountId, $nodes, $details['code'])[0]['units'] ?? []),
        ]);
    }

    public function saveSection(
        Request $request,
        OrganizationContext $context,
        ProfileResolver $resolver,
        SaveProfileSectionAction $action,
        string $secao,
    ): Response {
        $owner = $this->owner($context);
        Gate::authorize('account.institutional.view', $owner->node());

        $type = ProfileSectionType::tryFrom($secao);
        abort_if($type === null, 404);

        $draft = $resolver->resolve($owner, $type, ProfileState::Draft);
        $current = $draft->mode === 'override' && $draft->source !== 'platform' ? $draft->value : null;

        $data = SaveProfileSectionData::fromInput($secao, $request->all(), $owner->isEstablishment(), $current);

        $action->execute($data, $owner);
        ApiPage::toast($type->isOperational()
            ? $type->label().' salvos. A alteração já aparece nos sites publicados.'
            : $type->label().' salva como rascunho.');

        return response()->noContent();
    }

    public function impact(
        Request $request,
        OrganizationContext $context,
        ProfileImpactQuery $impact,
    ): JsonResponse {
        $owner = $this->owner($context);
        Gate::authorize('account.institutional.publish', $owner->node());

        $sections = array_values(array_filter(array_map(
            fn (mixed $value) => is_string($value) ? ProfileSectionType::tryFrom($value) : null,
            (array) $request->query('secoes', []),
        )));

        return response()->json($impact->execute($owner, $sections)->toArray(), headers: ['Cache-Control' => 'private, no-store']);
    }

    public function publish(
        Request $request,
        OrganizationContext $context,
        PublishProfileSectionsAction $action,
    ): Response {
        $owner = $this->owner($context);
        Gate::authorize('account.institutional.view', $owner->node());

        $data = PublishProfileSectionsData::fromInput($request->all());
        $result = $action->execute($data, $owner);

        ApiPage::toast($result->changed === []
            ? 'Nada a publicar: as seções escolhidas já estão publicadas.'
            : 'Seções publicadas.');

        return response()->noContent();
    }

    public function setAddress(
        Request $request,
        OrganizationContext $context,
        SetPublicAddressAction $action,
    ): Response {
        $owner = $this->owner($context);
        Gate::authorize('account.institutional.view', $owner->node());

        $action->execute(SetPublicAddressData::fromInput($request->all()), $owner);
        ApiPage::toast('Opção de endereço salva. A alteração já vale para os sites publicados.');

        return response()->noContent();
    }

    /**
     * @param  array<string, string>  $labels
     * @return list<array{key: string, label: string}>
     */
    private function options(array $labels): array
    {
        $options = [];

        foreach ($labels as $key => $label) {
            $options[] = ['key' => (string) $key, 'label' => $label];
        }

        return $options;
    }

    private function owner(OrganizationContext $context): ProfileOwner
    {
        $establishment = $context->establishment();

        if ($establishment !== null) {
            return ProfileOwner::establishment($context->accountId(), $establishment->company->id, $establishment->id);
        }

        $company = $context->company();
        abort_if($company === null, 404);

        return ProfileOwner::company($context->accountId(), $company->id);
    }
}
