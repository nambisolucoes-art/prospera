<?php

declare(strict_types=1);

namespace App\Domains\Midia\Domain\DTOs\Media;

use App\Domains\Midia\Domain\Enums\MediaPurpose;

final readonly class CreateMediaAssetDTO
{
    public function __construct(
        public MediaPurpose $purpose,
        public string $originalPath,
        public string $mime,
        public int $bytes,
        public int $width,
        public int $height,
        public string $sha256,
        public ?string $altText = null,
        public ?string $licenseNote = null,
    ) {}
}
