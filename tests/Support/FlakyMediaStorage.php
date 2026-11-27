<?php

namespace Tests\Support;

use App\Domains\Midia\Domain\Contracts\MediaStorage;
use App\Domains\Midia\Infrastructure\Storage\DiskMediaStorage;
use RuntimeException;

/** Armazenamento de teste que falha de propósito (gravar a N-ésima variante, remover o original) e delega o resto ao disco. */
final class FlakyMediaStorage implements MediaStorage
{
    public int $variantWrites = 0;

    public function __construct(
        private readonly DiskMediaStorage $disk,
        public ?int $failOnVariantWrite = null,
        public bool $failOnDelete = false,
    ) {}

    public function putOriginal(string $path, string $sourceFile): void
    {
        $this->disk->putOriginal($path, $sourceFile);
    }

    public function putVariant(string $path, string $contents): void
    {
        $this->variantWrites++;

        if ($this->failOnVariantWrite === $this->variantWrites) {
            throw new RuntimeException('falha simulada de gravação');
        }

        $this->disk->putVariant($path, $contents);
    }

    public function deleteOriginal(string $path): void
    {
        if ($this->failOnDelete) {
            throw new RuntimeException('falha simulada de remoção');
        }

        $this->disk->deleteOriginal($path);
    }

    public function deleteVariant(string $path): void
    {
        if ($this->failOnDelete) {
            throw new RuntimeException('falha simulada de remoção');
        }

        $this->disk->deleteVariant($path);
    }

    public function readOriginal(string $path)
    {
        return $this->disk->readOriginal($path);
    }

    public function readVariant(string $path)
    {
        return $this->disk->readVariant($path);
    }

    public function originalExists(string $path): bool
    {
        return $this->disk->originalExists($path);
    }

    public function variantExists(string $path): bool
    {
        return $this->disk->variantExists($path);
    }

    public function listOriginals(): array
    {
        return $this->disk->listOriginals();
    }

    public function listVariants(): array
    {
        return $this->disk->listVariants();
    }
}
