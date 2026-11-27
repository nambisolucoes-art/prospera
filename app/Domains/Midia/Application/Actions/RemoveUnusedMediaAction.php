<?php

declare(strict_types=1);

namespace App\Domains\Midia\Application\Actions;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Midia\Application\Queries\MediaIsInUseQuery;
use App\Domains\Midia\Domain\Exceptions\MediaInUse;
use App\Domains\Midia\Domain\Models\MediaAsset;
use App\Domains\Midia\Domain\Models\MediaVariant;
use App\Domains\Midia\Domain\ValueObjects\StoredMediaFiles;

/**
 * Remove de vez uma mídia que não está em uso: linhas e arquivos. É a operação reutilizável pela exclusão, pelo
 * `media:purge` e pelos eventos de publicação, despublicação e reversão das etapas futuras (sem autorização própria: quem
 * chama já autorizou). Primeiro as linhas, numa transação; depois os arquivos. Se um arquivo não puder ser removido, o
 * banco já não o referencia e o `media:purge` o encontra como resíduo, sem estado inconsistente.
 */
final readonly class RemoveUnusedMediaAction
{
    public function __construct(
        private MediaIsInUseQuery $isInUse,
        private DiscardMediaFilesAction $discardFiles,
    ) {}

    /**
     * @throws MediaInUse
     */
    public function execute(MediaAsset $media): void
    {
        $files = AuditLog::transaction(function () use ($media): ?StoredMediaFiles {
            $lockedMedia = MediaAsset::where('account_id', $media->account_id)
                ->where('company_id', $media->company_id)
                ->lockForUpdate()
                ->find($media->id);

            // Já removida por outra operação: nada a fazer.
            if ($lockedMedia === null) {
                return null;
            }

            if ($this->isInUse->execute($lockedMedia)) {
                throw new MediaInUse;
            }

            $variants = MediaVariant::where('media_asset_id', $lockedMedia->id)->lockForUpdate()->get();
            $paths = $variants->pluck('public_path', 'kind')->all();

            foreach ($variants as $variant) {
                $variant->delete();
            }

            $lockedMedia->delete();

            return new StoredMediaFiles($lockedMedia->original_path, $paths);
        });

        if ($files !== null) {
            $this->discardFiles->execute($files);
        }
    }
}
