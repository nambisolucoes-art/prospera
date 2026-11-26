<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Domain\ValueObjects\Hours;

/**
 * Resumo do alcance de uma exceção da empresa: as unidades ativas que herdam o horário (sempre alcançadas) e as que têm
 * horário próprio (só alcançadas com `applies_to_overrides`). `token` identifica exatamente esses dois conjuntos e o
 * pedido confirmado: o servidor o recalcula ao gravar, e uma divergência obriga nova confirmação.
 */
final readonly class HourExceptionImpact
{
    /**
     * @param  list<array{code: string, name: string}>  $inheriting
     * @param  list<array{code: string, name: string}>  $overriding
     */
    public function __construct(
        public array $inheriting,
        public array $overriding,
        public int $disabled,
        public string $token,
    ) {}

    /** @return array{inheriting: list<array{code: string, name: string}>, overriding: list<array{code: string, name: string}>, disabled: int, token: string} */
    public function toArray(): array
    {
        return [
            'inheriting' => $this->inheriting,
            'overriding' => $this->overriding,
            'disabled' => $this->disabled,
            'token' => $this->token,
        ];
    }
}
