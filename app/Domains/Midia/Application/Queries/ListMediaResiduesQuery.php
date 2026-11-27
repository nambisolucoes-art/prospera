<?php

declare(strict_types=1);

namespace App\Domains\Midia\Application\Queries;

use App\Domains\Midia\Domain\Contracts\MediaStorage;
use Illuminate\Support\Facades\DB;

/**
 * Resíduos da biblioteca, só leitura: arquivos nos discos gerenciados sem linha no banco (mais antigos que a tolerância,
 * para não colidir com um envio em andamento), linhas cujo arquivo sumiu e ativos sem nenhuma variante. Só olha os
 * prefixos gerenciados (`o/` e `v/`): o que está fora deles nunca é resíduo da biblioteca.
 */
final readonly class ListMediaResiduesQuery
{
    public function __construct(
        private MediaStorage $storage,
    ) {}

    /**
     * @return array{orphan_originals: list<string>, orphan_variants: list<string>, missing_files: list<int>, without_variants: list<int>}
     */
    public function execute(int $graceMinutes): array
    {
        $limit = time() - $graceMinutes * 60;

        $originalPaths = array_flip(DB::table('media_assets')->pluck('original_path')->all());
        $variantPaths = array_flip(DB::table('media_variants')->pluck('public_path')->all());

        $orphanOriginals = [];
        $orphanVariants = [];

        foreach ($this->storage->listOriginals() as $file) {
            if (str_starts_with($file['path'], 'o/') && ! isset($originalPaths[$file['path']]) && $file['modified'] <= $limit) {
                $orphanOriginals[] = $file['path'];
            }
        }

        foreach ($this->storage->listVariants() as $file) {
            if (str_starts_with($file['path'], 'v/') && ! isset($variantPaths[$file['path']]) && $file['modified'] <= $limit) {
                $orphanVariants[] = $file['path'];
            }
        }

        $missing = [];

        foreach (DB::table('media_assets')->get(['id', 'original_path']) as $asset) {
            if (! $this->storage->originalExists($asset->original_path)) {
                $missing[] = (int) $asset->id;
            }
        }

        foreach (DB::table('media_variants')->get(['media_asset_id', 'public_path']) as $variant) {
            if (! $this->storage->variantExists($variant->public_path)) {
                $missing[] = (int) $variant->media_asset_id;
            }
        }

        $without = DB::table('media_assets')
            ->whereNotExists(fn ($query) => $query->select(DB::raw('1'))->from('media_variants')->whereColumn('media_variants.media_asset_id', 'media_assets.id'))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        return [
            'orphan_originals' => $orphanOriginals,
            'orphan_variants' => $orphanVariants,
            'missing_files' => array_values(array_unique($missing)),
            'without_variants' => array_values($without),
        ];
    }
}
