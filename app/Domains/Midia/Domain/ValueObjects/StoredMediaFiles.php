<?php

declare(strict_types=1);

namespace App\Domains\Midia\Domain\ValueObjects;

/** Os caminhos opacos já gravados no armazenamento: o original (privado) e, por tipo de variante, o derivado. */
final readonly class StoredMediaFiles
{
    /** @param array<string, string> $variantPaths tipo da variante para caminho */
    public function __construct(
        public string $originalPath,
        public array $variantPaths,
    ) {}
}
