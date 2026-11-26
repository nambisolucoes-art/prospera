<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Domain\Contracts;

use Illuminate\Validation\ValidationException;

/**
 * Valor tipado de uma seção do perfil institucional. A herança é por seção inteira, então cada seção valida o
 * próprio objeto completo. Campos ausentes no envio mantêm o valor atual, e limpar exige enviar vazio de propósito.
 */
interface SectionValue
{
    /**
     * @param  array<string,mixed>  $input
     * @param  array<string,mixed>  $current
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input, array $current): static;

    /** O vazio intencional válido da seção. */
    public static function empty(): static;

    /** O padrão da plataforma, usado quando nenhuma empresa configurou a seção. */
    public static function default(): static;

    /** @return array<string,mixed> */
    public function toArray(): array;
}
