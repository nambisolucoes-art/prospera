<?php

declare(strict_types=1);

namespace App\Domains\Midia\Infrastructure\Storage;

use App\Domains\Midia\Domain\Contracts\MediaStorage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Implementação por discos do Laravel. Os discos vêm de `config/media.php` e nenhum deles fica sob `public/`. */
final class DiskMediaStorage implements MediaStorage
{
    public function putOriginal(string $path, string $sourceFile): void
    {
        $stream = fopen($sourceFile, 'rb');

        if ($stream === false) {
            throw new RuntimeException('Arquivo de envio ilegível.');
        }

        try {
            $this->originals()->writeStream($path, $stream);
        } finally {
            fclose($stream);
        }
    }

    public function putVariant(string $path, string $contents): void
    {
        $this->variants()->put($path, $contents);
    }

    public function deleteOriginal(string $path): void
    {
        $this->originals()->delete($path);
    }

    public function deleteVariant(string $path): void
    {
        $this->variants()->delete($path);
    }

    public function readOriginal(string $path)
    {
        return $this->originals()->readStream($path);
    }

    public function readVariant(string $path)
    {
        return $this->variants()->readStream($path);
    }

    public function originalExists(string $path): bool
    {
        return $this->originals()->exists($path);
    }

    public function variantExists(string $path): bool
    {
        return $this->variants()->exists($path);
    }

    public function listOriginals(): array
    {
        return $this->listing($this->originals());
    }

    public function listVariants(): array
    {
        return $this->listing($this->variants());
    }

    private function originals(): Filesystem
    {
        return Storage::disk((string) config('media.disks.originals'));
    }

    private function variants(): Filesystem
    {
        return Storage::disk((string) config('media.disks.variants'));
    }

    /** @return list<array{path: string, modified: int}> */
    private function listing(Filesystem $disk): array
    {
        $files = [];

        foreach ($disk->allFiles() as $path) {
            $files[] = ['path' => $path, 'modified' => $disk->lastModified($path)];
        }

        return $files;
    }
}
