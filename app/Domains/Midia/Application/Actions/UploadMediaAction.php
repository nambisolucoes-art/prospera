<?php

declare(strict_types=1);

namespace App\Domains\Midia\Application\Actions;

use App\Domains\Auditoria\Application\Actions\AuditContext;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Midia\Application\Data\UploadMediaData;
use App\Domains\Midia\Domain\Contracts\ImageInspector;
use App\Domains\Midia\Domain\Contracts\MediaPipelineProbe;
use App\Domains\Midia\Domain\DTOs\Media\CreateMediaAssetDTO;
use App\Domains\Midia\Domain\Exceptions\InvalidMedia;
use App\Domains\Midia\Domain\Models\MediaAsset;
use App\Domains\Midia\Domain\Models\MediaVariant;
use App\Domains\Midia\Domain\ValueObjects\MediaOwner;
use App\Support\Exceptions\ResourceNotAvailable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Envia uma imagem para a biblioteca da empresa (corporativa) ou da unidade (local), de forma síncrona e atômica.
 *
 * Ordem: autoriza; barreiras anteriores à decodificação (bytes, tipo pelo conteúdo, dimensões, memória); gera os
 * derivados sob o orçamento de tempo, sem gravar nada; grava os arquivos (compensando se falhar); e só então, numa
 * transação, trava o dono e grava o ativo e as variantes. Falha depois dos arquivos remove os arquivos. Mídia incompleta
 * nunca existe no banco, e um processo morto no meio deixa só resíduo de arquivo, que o `media:purge` encontra.
 */
final readonly class UploadMediaAction
{
    public function __construct(
        private ImageInspector $inspector,
        private GenerateMediaVariantsAction $generateVariants,
        private WriteMediaFilesAction $writeFiles,
        private DiscardMediaFilesAction $discardFiles,
        private LockMediaOwnerAction $lockOwner,
        private MediaPipelineProbe $probe,
        private AuditContext $audit,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws InvalidMedia
     * @throws ResourceNotAvailable
     * @throws Throwable
     */
    public function execute(UploadMediaData $data, MediaOwner $owner): MediaAsset
    {
        $startedAt = (float) hrtime(true);
        $file = (string) $data->file->getRealPath();

        Gate::authorize('account.media.create', $owner->node());

        $inspection = $this->inspector->inspect($file);

        $variants = $this->generateVariants->execute($file, $inspection, $data->purpose, $startedAt);

        $stored = $this->writeFiles->execute($file, $variants);

        try {
            $this->probe->reach('before_commit');

            return AuditLog::transaction(function () use ($data, $owner, $inspection, $variants, $stored): MediaAsset {
                $this->lockOwner->execute($owner);

                $assetDTO = new CreateMediaAssetDTO(
                    purpose: $data->purpose,
                    originalPath: $stored->originalPath,
                    mime: $inspection->mime,
                    bytes: $inspection->bytes,
                    width: $inspection->width,
                    height: $inspection->height,
                    sha256: $inspection->sha256,
                    altText: $data->altText,
                    licenseNote: $data->licenseNote,
                );

                $uploadedBy = $this->audit->actorId ?? (int) auth()->id();

                $asset = MediaAsset::register($assetDTO, $owner, $uploadedBy);
                $asset->saveOrFail();

                foreach ($variants as $variant) {
                    MediaVariant::register($asset, $variant, $stored->variantPaths[$variant->kind])->saveOrFail();
                }

                return $asset;
            });
        } catch (Throwable $failure) {
            $this->discardFiles->execute($stored);

            throw $failure;
        }
    }
}
