<?php

declare(strict_types=1);

namespace App\Domains\Midia\Domain\ValueObjects;

use App\Domains\Acessos\Domain\ValueObjects\AccessNode;

/** Dono de uma mídia: a biblioteca corporativa da empresa ou a biblioteca local de uma unidade, sempre dentro de uma conta. */
final readonly class MediaOwner
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
}
