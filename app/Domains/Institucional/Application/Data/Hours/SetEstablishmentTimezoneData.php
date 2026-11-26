<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Data\Hours;

use DateTimeZone;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Fuso IANA de um estabelecimento. Siglas (`EST`) e deslocamentos (`-03:00`) não são fusos IANA e são recusados. */
final readonly class SetEstablishmentTimezoneData
{
    public function __construct(
        public string $timezone,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input): self
    {
        if (is_string($input['timezone'] ?? null)) {
            $input['timezone'] = trim($input['timezone']);
        }

        $values = Validator::validate($input, self::rules(), self::messages());

        return new self(timezone: $values['timezone']);
    }

    /** @return array<string,list<mixed>> */
    public static function rules(): array
    {
        return [
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers(DateTimeZone::ALL))],
        ];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return [
            'timezone.required' => 'Escolha um fuso da lista, como America/Sao_Paulo ou America/Manaus.',
            'timezone.in' => 'Escolha um fuso da lista, como America/Sao_Paulo ou America/Manaus.',
        ];
    }
}
