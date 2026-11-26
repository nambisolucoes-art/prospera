<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Domain\ValueObjects;

/**
 * Valor efetivo de uma seção para um dono e um estado (seção 09.3).
 *
 * `source`: de onde vem o valor (`company`, `establishment` ou `platform`). `mode`: o modo próprio do dono
 * (`override`, `inherit`, `disabled`) ou `default` quando a empresa nunca configurou a seção. `inherited_from`: de
 * onde o valor foi herdado, nulo quando é próprio.
 */
final readonly class ResolvedSection
{
    /** @param array<string,mixed>|null $value */
    public function __construct(
        public ?array $value,
        public string $source,
        public string $mode,
        public ?string $inheritedFrom,
    ) {}

    /** @return array{value: array<string,mixed>|null, source: string, mode: string, inherited_from: string|null} */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'source' => $this->source,
            'mode' => $this->mode,
            'inherited_from' => $this->inheritedFrom,
        ];
    }
}
