<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Domain\ValueObjects;

use App\Domains\Institucional\Domain\Enums\ProfileSectionType;

/**
 * Resumo do impacto de publicar seções de um dono. Sempre recalculado no servidor: o cliente só confirma os números.
 * Não há contagem de sites enquanto a tabela `sites` não existir (S10).
 */
final readonly class ProfileImpact
{
    /** @param list<ProfileSectionType> $changed seções com diferença entre trabalho e publicado */
    public function __construct(
        public array $changed,
        public int $inheriting,
        public int $overriding,
    ) {}

    /** @return array{changed: list<string>, inheriting: int, overriding: int} */
    public function toArray(): array
    {
        return [
            'changed' => array_map(fn (ProfileSectionType $type) => $type->value, $this->changed),
            'inheriting' => $this->inheriting,
            'overriding' => $this->overriding,
        ];
    }
}
