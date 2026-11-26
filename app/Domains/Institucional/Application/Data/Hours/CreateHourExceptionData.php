<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Data\Hours;

use App\Domains\Institucional\Domain\Enums\HourExceptionKind;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Cadastro de uma exceção de horário. Datas de calendário (`Y-m-d`), inclusivas nas duas pontas;
 * sem último dia, a exceção vale um dia só. Aqui só o formato é validado: ordem e sobreposição dos turnos, período
 * máximo e a regra de a exceção da empresa alcançar unidades com horário próprio são invariantes do Model.
 */
final readonly class CreateHourExceptionData
{
    /** @param list<array{start: string, end: string}>|null $shifts */
    public function __construct(
        public HourExceptionKind $kind,
        public string $startsOn,
        public string $endsOn,
        public ?array $shifts,
        public ?string $note,
        public bool $appliesToOverrides,
        public ?string $impactToken,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input): self
    {
        if (is_string($input['note'] ?? null)) {
            $input['note'] = trim(preg_replace('/\s+/', ' ', strip_tags($input['note'])) ?? '');
        }

        $values = Validator::validate($input, self::rules(), self::messages());

        return new self(
            kind: HourExceptionKind::from($values['kind']),
            startsOn: $values['starts_on'],
            endsOn: $values['ends_on'] ?? $values['starts_on'],
            shifts: $values['shifts'] ?? null,
            note: $values['note'] ?? null,
            appliesToOverrides: (bool) ($values['applies_to_overrides'] ?? false),
            impactToken: $values['impact_token'] ?? null,
        );
    }

    /** @return array<string,list<mixed>> */
    public static function rules(): array
    {
        $time = 'regex:/^([01]\d|2[0-3]):[0-5]\d$/';
        $endTime = 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00)$/';

        return [
            'kind' => ['required', Rule::enum(HourExceptionKind::class)],
            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on', 'before_or_equal:2100-12-31'],
            'shifts' => ['nullable', 'array', 'max:6'],
            'shifts.*.start' => ['required', 'string', $time],
            'shifts.*.end' => ['required', 'string', $endTime],
            'note' => ['nullable', 'string', 'max:160'],
            'applies_to_overrides' => ['sometimes', 'boolean'],
            'impact_token' => ['nullable', 'string', 'size:64'],
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
            'starts_on.after_or_equal' => 'Informe uma data entre 2000 e 2100.',
            'starts_on.before_or_equal' => 'Informe uma data entre 2000 e 2100.',
            'ends_on.date_format' => 'Informe uma data válida.',
            'ends_on.after_or_equal' => 'O último dia não pode ser antes do primeiro dia.',
            'ends_on.before_or_equal' => 'Informe uma data entre 2000 e 2100.',
            'shifts.array' => 'Informe os turnos do dia.',
            'shifts.max' => 'Informe até 6 turnos.',
            'shifts.*.start.required' => 'Informe a hora de início.',
            'shifts.*.start.regex' => 'Informe a hora de início no formato HH:MM, de 00:00 a 23:59.',
            'shifts.*.end.required' => 'Informe a hora de término.',
            'shifts.*.end.regex' => 'Informe a hora de término no formato HH:MM, até 24:00.',
            'note.max' => 'A observação deve ter até 160 caracteres.',
            'applies_to_overrides.boolean' => 'Informe sim ou não.',
            'impact_token.size' => 'Confirme o resumo de impacto novamente.',
        ];
    }
}
