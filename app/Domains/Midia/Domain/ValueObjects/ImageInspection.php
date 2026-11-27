<?php

declare(strict_types=1);

namespace App\Domains\Midia\Domain\ValueObjects;

/** Resultado das barreiras anteriores à decodificação: o que o conteúdo do arquivo realmente é. */
final readonly class ImageInspection
{
    public function __construct(
        public string $mime,
        public int $bytes,
        public int $width,
        public int $height,
        public string $sha256,
        public int $orientation,
        public int $estimatedMemory,
    ) {}

    public function megapixels(): float
    {
        return $this->width * $this->height / 1_000_000;
    }

    /** Orientações 5 a 8 trocam largura e altura na imagem exibida. */
    public function swapsAxes(): bool
    {
        return $this->orientation >= 5;
    }
}
