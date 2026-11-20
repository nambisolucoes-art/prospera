<?php

namespace App\Support;

/**
 * Documento fiscal brasileiro: CPF, CNPJ completo (numérico ou alfanumérico) ou raiz de CNPJ.
 *
 * A comparação de pessoa jurídica é feita pela raiz normalizada, nunca pelo texto formatado.
 */
final readonly class TaxDocument
{
    public const CPF = 'cpf';

    public const CNPJ = 'cnpj';

    public const CNPJ_ROOT = 'cnpj-root';

    private const CNPJ_WEIGHTS = [
        [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
        [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
    ];

    private function __construct(
        private string $value,
        private string $type,
    ) {}

    public static function normalize(string $value): string
    {
        $withoutMask = preg_replace('/[.\/\s()-]+/', '', trim($value)) ?? '';

        return strtoupper($withoutMask);
    }

    public static function cpf(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $normalized = self::normalize($value);

        return self::isValidCpf($normalized) ? new self($normalized, self::CPF) : null;
    }

    public static function cnpj(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $normalized = self::normalize($value);

        return self::isValidCnpj($normalized) ? new self($normalized, self::CNPJ) : null;
    }

    /**
     * Aceita a raiz de 8 posições ou um CNPJ completo válido, do qual extrai a raiz.
     */
    public static function cnpjRoot(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $normalized = self::normalize($value);

        if (preg_match('/^[A-Z0-9]{8}$/', $normalized) === 1) {
            return new self($normalized, self::CNPJ_ROOT);
        }

        $cnpj = self::cnpj($normalized);

        return $cnpj === null ? null : new self((string) $cnpj->root(), self::CNPJ_ROOT);
    }

    public static function isValidCpf(string $normalized): bool
    {
        $validFormat = preg_match('/^\d{11}$/', $normalized) === 1;
        $repeatedDigits = preg_match('/^(\d)\1{10}$/', $normalized) === 1;

        if (! $validFormat || $repeatedDigits) {
            return false;
        }

        for ($length = 9; $length < 11; $length++) {
            $sum = 0;

            for ($index = 0; $index < $length; $index++) {
                $sum += (int) $normalized[$index] * ($length + 1 - $index);
            }

            $digit = (10 * $sum) % 11;

            if ((int) $normalized[$length] !== ($digit === 10 ? 0 : $digit)) {
                return false;
            }
        }

        return true;
    }

    public static function isValidCnpj(string $normalized): bool
    {
        // Desde a IN RFB 2.229/2024, raiz e ordem podem ter letras; os dígitos verificadores são numéricos.
        $validFormat = preg_match('/^[A-Z0-9]{12}\d{2}$/', $normalized) === 1;
        $repeatedCharacters = preg_match('/^(.)\1{13}$/', $normalized) === 1;

        if (! $validFormat || $repeatedCharacters) {
            return false;
        }

        $base = substr($normalized, 0, 12);

        foreach (self::CNPJ_WEIGHTS as $weights) {
            $sum = 0;

            foreach ($weights as $index => $weight) {
                // Cada caractere vale o código ASCII menos 48.
                $sum += (ord($base[$index]) - 48) * $weight;
            }

            $rest = $sum % 11;
            $base .= (string) ($rest < 2 ? 0 : 11 - $rest);
        }

        return $base === $normalized;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function isCpf(): bool
    {
        return $this->type === self::CPF;
    }

    public function isCnpj(): bool
    {
        return $this->type === self::CNPJ;
    }

    public function root(): ?string
    {
        return match ($this->type) {
            self::CNPJ => substr($this->value, 0, 8),
            self::CNPJ_ROOT => $this->value,
            default => null,
        };
    }

    public function order(): ?string
    {
        return $this->type === self::CNPJ ? substr($this->value, 8, 4) : null;
    }

    public function isSameLegalEntityAs(self $other): bool
    {
        if ($this->root() !== null && $other->root() !== null) {
            return $this->root() === $other->root();
        }

        // Empresário individual: a pessoa jurídica é identificada pelo próprio CPF.
        return $this->isCpf()
            && $other->isCpf()
            && $this->value === $other->value;
    }

    public function formatted(): string
    {
        $pattern = match ($this->type) {
            self::CPF => '/^(.{3})(.{3})(.{3})(.{2})$/',
            self::CNPJ => '/^(.{2})(.{3})(.{3})(.{4})(.{2})$/',
            default => '/^(.{2})(.{3})(.{3})$/',
        };

        $replacement = match ($this->type) {
            self::CPF => '$1.$2.$3-$4',
            self::CNPJ => '$1.$2.$3/$4-$5',
            default => '$1.$2.$3',
        };

        return (string) preg_replace($pattern, $replacement, $this->value);
    }
}
