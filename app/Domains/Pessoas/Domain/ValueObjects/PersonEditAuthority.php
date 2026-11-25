<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Domain\ValueObjects;

/**
 * O que o operador pode alterar no cadastro central de uma pessoa (seção 10.2.1).
 *
 * Identificação exige cobrir todas as empresas com relação ativa; contatos e endereço, pelo menos uma.
 * Sem relação ativa, qualquer atribuição com account.people.update edita os dois.
 */
final readonly class PersonEditAuthority
{
    public function __construct(
        public bool $allowed,
        public bool $identification,
        public bool $contacts,
    ) {}

    public static function denied(): self
    {
        return new self(false, false, false);
    }
}
