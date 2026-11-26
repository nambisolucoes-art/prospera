<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Data\Hours;

use App\Domains\Institucional\Domain\Enums\HourExceptionKind;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Pedido do resumo de alcance de uma exceção da empresa: o tipo e o período que serão confirmados. */
final readonly class HourExceptionImpactData
{
    public function __construct(
        public HourExceptionKind $kind,
        public string $startsOn,
        public string $endsOn,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input): self
    {
        $values = Validator::validate($input, self::rules(), self::messages());

        return new self(
            kind: HourExceptionKind::from($values['kind']),
            startsOn: $values['starts_on'],
            endsOn: $values['ends_on'] ?? $values['starts_on'],
        );
    }

    /** @return array<string,list<mixed>> */
    public static function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(HourExceptionKind::class)],
            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on', 'before_or_equal:2100-12-31'],
        ];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return [
            'kind.required' => 'Escolha feriado, fechamento temporário ou horário especial.',
            'kind.enum' => 'Escolha feriado, fechamento temporário ou horário especial.',
            'starts_on.required' => 'Informe o primeiro dia.',
            'starts_on.date_format' => 'Informe uma data válida.',
            'ends_on.date_format' => 'Informe uma data válida.',
            'ends_on.after_or_equal' => 'O último dia não pode ser antes do primeiro dia.',
        ];
    }
}
