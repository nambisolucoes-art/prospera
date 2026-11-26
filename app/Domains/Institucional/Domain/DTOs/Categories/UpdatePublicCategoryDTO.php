<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Domain\DTOs\Categories;

final readonly class UpdatePublicCategoryDTO
{
    public function __construct(
        public string $name,
        public string $normalizedName,
        public string $slug,
        public int $sortOrder,
        public ?string $seoTitle,
        public ?string $seoDescription,
        public bool $active,
    ) {}
}
