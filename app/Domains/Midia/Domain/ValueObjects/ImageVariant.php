<?php

declare(strict_types=1);

namespace App\Domains\Midia\Domain\ValueObjects;

/** Um derivado gerado em memória, antes de ser gravado. `kind` é a largura nominal (`w640`). */
final readonly class ImageVariant
{
    public function __construct(
        public string $kind,
        public string $mime,
        public int $width,
        public int $height,
        public string $contents,
    ) {}

    public function extension(): string
    {
        return match ($this->mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    public function bytes(): int
    {
        return strlen($this->contents);
    }
}
