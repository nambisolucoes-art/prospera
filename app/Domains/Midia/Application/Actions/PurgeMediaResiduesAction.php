<?php

declare(strict_types=1);

namespace App\Domains\Midia\Application\Actions;

use App\Domains\Midia\Application\Queries\ListMediaResiduesQuery;
use App\Domains\Midia\Application\Queries\MediaIsInUseQuery;
use App\Domains\Midia\Domain\Contracts\MediaStorage;
use App\Domains\Midia\Domain\Enums\MediaStatus;
use App\Domains\Midia\Domain\Models\MediaAsset;

/**
 * Operação do `media:purge`. No modo simulado só conta; no real remove os arquivos órfãos dos discos gerenciados e as
 * mídias com exclusão solicitada que já não estão em uso. Idempotente: uma segunda execução não encontra mais nada.
 */
final readonly class PurgeMediaResiduesAction
{
    public function __construct(
        private ListMediaResiduesQuery $residues,
        private MediaStorage $storage,
        private MediaIsInUseQuery $isInUse,
        private RemoveUnusedMediaAction $removeUnused,
    ) {}

    /**
     * @return array{orphan_originals: int, orphan_variants: int, released: int, missing_files: list<int>, without_variants: list<int>}
     */
    public function execute(bool $dryRun, int $graceMinutes): array
    {
        $found = $this->residues->execute($graceMinutes);

        $releasable = MediaAsset::where('status', MediaStatus::RemovalRequested->value)
            ->get()
            ->filter(fn (MediaAsset $media): bool => ! $this->isInUse->execute($media));

        if (! $dryRun) {
            foreach ($found['orphan_originals'] as $path) {
                $this->storage->deleteOriginal($path);
            }

            foreach ($found['orphan_variants'] as $path) {
                $this->storage->deleteVariant($path);
            }

            foreach ($releasable as $media) {
                $this->removeUnused->execute($media);
            }
        }

        return [
            'orphan_originals' => count($found['orphan_originals']),
            'orphan_variants' => count($found['orphan_variants']),
            'released' => $releasable->count(),
            'missing_files' => $found['missing_files'],
            'without_variants' => $found['without_variants'],
        ];
    }
}
