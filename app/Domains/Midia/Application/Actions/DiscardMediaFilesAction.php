<?php

declare(strict_types=1);

namespace App\Domains\Midia\Application\Actions;

use App\Domains\Midia\Domain\Contracts\MediaStorage;
use App\Domains\Midia\Domain\ValueObjects\StoredMediaFiles;
use Throwable;

/**
 * Compensação do armazenamento: remove o original e os derivados já gravados. Uma falha ao remover um arquivo não
 * impede a remoção dos demais; o que sobrar é resíduo sem linha no banco e o `media:purge` o encontra.
 */
final readonly class DiscardMediaFilesAction
{
    public function __construct(
        private MediaStorage $storage,
    ) {}

    public function execute(StoredMediaFiles $files): void
    {
        foreach ($files->variantPaths as $path) {
            try {
                $this->storage->deleteVariant($path);
            } catch (Throwable) {
                continue;
            }
        }

        try {
            $this->storage->deleteOriginal($files->originalPath);
        } catch (Throwable) {
            return;
        }
    }
}
