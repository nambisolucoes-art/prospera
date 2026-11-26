<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Domain\ValueObjects;

use App\Domains\Acessos\Domain\ValueObjects\AccessNode;

/** Dono de um perfil institucional: uma empresa ou um dos seus estabelecimentos, sempre dentro de uma conta. */
final readonly class ProfileOwner
{
    private function __construct(
        public int $accountId,
        public int $companyId,
        public ?int $establishmentId,
    ) {}

    public static function company(int $accountId, int $companyId): self
    {
        return new self($accountId, $companyId, null);
    }

    public static function establishment(int $accountId, int $companyId, int $establishmentId): self
    {
        return new self($accountId, $companyId, $establishmentId);
    }

    public function isEstablishment(): bool
    {
        return $this->establishmentId !== null;
    }

    public function node(): AccessNode
    {
        return $this->establishmentId === null
            ? AccessNode::company($this->accountId, $this->companyId)
            : AccessNode::establishment($this->accountId, $this->companyId, $this->establishmentId);
    }

    public function key(): string
    {
        return $this->accountId.':'.$this->companyId.':'.($this->establishmentId ?? '-');
    }
}
