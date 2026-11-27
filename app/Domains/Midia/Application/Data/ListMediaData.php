<?php

declare(strict_types=1);

namespace App\Domains\Midia\Application\Data;

use App\Domains\Midia\Domain\Enums\MediaPurpose;

/** Filtros da biblioteca, já saneados: uso, escopo (corporativa ou local) e busca no texto alternativo. */
final readonly class ListMediaData
{
    public const PAGE_SIZE = 24;

    public function __construct(
        public ?MediaPurpose $purpose,
        public string $scope,
        public string $search,
    ) {}

    /** @param array<string,mixed> $input */
    public static function fromInput(array $input): self
    {
        return new self(
            purpose: is_string($input['purpose'] ?? null) ? MediaPurpose::tryFrom($input['purpose']) : null,
            scope: in_array($input['scope'] ?? null, ['corporate', 'local'], true) ? $input['scope'] : '',
            search: is_string($input['search'] ?? null) ? mb_substr(trim($input['search']), 0, 100) : '',
        );
    }

    /** @return array{purpose: string, scope: string, search: string} */
    public function toArray(): array
    {
        return ['purpose' => $this->purpose === null ? '' : $this->purpose->value, 'scope' => $this->scope, 'search' => $this->search];
    }
}
