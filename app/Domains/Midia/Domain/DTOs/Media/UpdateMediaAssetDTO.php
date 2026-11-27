<?php

declare(strict_types=1);

namespace App\Domains\Midia\Domain\DTOs\Media;

final readonly class UpdateMediaAssetDTO
{
    public function __construct(
        public ?string $altText = null,
        public ?string $licenseNote = null,
    ) {}
}
