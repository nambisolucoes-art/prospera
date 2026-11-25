<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Domain\ValueObjects;

final readonly class AccessNode
{
    private function __construct(
        public int $accountId,
        public string $type,
        public ?int $companyId = null,
        public ?int $establishmentId = null,
    ) {}

    public static function tenant(int $accountId): self
    {
        return new self($accountId, 'tenant');
    }

    public static function company(int $accountId, int $companyId): self
    {
        return new self($accountId, 'company', $companyId);
    }

    public static function establishment(int $accountId, int $companyId, int $establishmentId): self
    {
        return new self($accountId, 'establishment', $companyId, $establishmentId);
    }

    public function acceptsPermission(string $slug): bool
    {
        if (str_starts_with($slug, 'self.')) {
            return true;
        }

        if ($this->type === 'tenant') {
            return str_starts_with($slug, 'account.');
        }

        $targets = config('permissions.targets', [])[$slug] ?? [];
        $covered = $this->type === 'company' ? ['company', 'establishment', 'person'] : ['establishment', 'person'];

        return array_intersect($targets, $covered) !== [];
    }
}
