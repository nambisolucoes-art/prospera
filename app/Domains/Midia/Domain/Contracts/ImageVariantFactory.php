<?php

declare(strict_types=1);

namespace App\Domains\Midia\Domain\Contracts;

use App\Domains\Midia\Domain\Enums\MediaPurpose;
use App\Domains\Midia\Domain\Exceptions\InvalidMedia;
use App\Domains\Midia\Domain\ValueObjects\ImageInspection;
use App\Domains\Midia\Domain\ValueObjects\ImageVariant;

/** Decodifica uma imagem já inspecionada e gera os derivados do uso, sem metadados e sem ampliar. */
interface ImageVariantFactory
{
    /**
     * @param  callable(): void  $checkpoint  chamado entre as variantes; lança se o orçamento de tempo estourou
     * @return list<ImageVariant>
     *
     * @throws InvalidMedia
     */
    public function make(string $file, ImageInspection $inspection, MediaPurpose $purpose, callable $checkpoint): array;
}
