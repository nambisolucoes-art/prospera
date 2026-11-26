<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Data\Profile\Sections;

use App\Domains\Institucional\Domain\Contracts\SectionValue;
use App\Domains\Institucional\Domain\Enums\DayKind;
use App\Domains\Institucional\Domain\Enums\Weekday;
use App\Domains\Institucional\Domain\Exceptions\InvalidHourException;
use App\Domains\Institucional\Domain\ValueObjects\Hours\DaySchedule;
use App\Domains\Institucional\Domain\ValueObjects\Hours\Shifts;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Horário semanal (`hours_weekly`): para cada dia, o tipo (aberto, sob agendamento ou fechado) e os turnos. O fuso não
 * entra aqui: é a coluna `establishments.timezone` (PD2-08, DA2-23), e a hora cadastrada é a hora local da unidade.
 *
 * Como toda seção, é herança por seção inteira com PATCH parcial: um dia ausente mantém o valor atual; num dia
 * presente, `kind` ou `shifts` ausentes mantêm o atual, exceto os turnos de um dia que passa a fechado, que são
 * descartados. Como o middleware converte `''` em `null`, `shifts: null` é a lista vazia enviada de propósito. O formato
 * é validado por `rules()`; ordem, sobreposição e a coerência entre tipo e turnos são invariantes dos Value Objects.
 * A disponibilidade ("aberto agora") nunca é gravada: só a configuração.
 */
final readonly class HoursWeeklyValue implements SectionValue
{
    /** @param array<string, DaySchedule> $days chave do dia (`mon` a `sun`) para o atendimento do dia */
    private function __construct(
        public array $days,
    ) {}

    public static function fromInput(array $input, array $current): static
    {
        $merged = self::merge($input, $current);

        $values = Validator::validate($merged, self::rules(), self::messages());

        $days = [];

        foreach (Weekday::cases() as $weekday) {
            $day = $values['days'][$weekday->value];

            $path = 'days.'.$weekday->value;

            try {
                $shifts = Shifts::from($day['shifts']);
            } catch (InvalidHourException $failure) {
                throw $failure->prefixed($path.'.shifts');
            }

            try {
                $days[$weekday->value] = DaySchedule::of(DayKind::from($day['kind']), $shifts);
            } catch (InvalidHourException $failure) {
                throw $failure->prefixed($path);
            }
        }

        return new self($days);
    }

    /** @return array<string,list<mixed>> */
    public static function rules(): array
    {
        $keys = implode(',', array_map(fn (Weekday $day): string => $day->value, Weekday::cases()));

        return [
            'days' => ['required', 'array:'.$keys],
            'days.*' => ['required', 'array'],
            'days.*.kind' => ['required', Rule::enum(DayKind::class)],
            'days.*.shifts' => ['present', 'array', 'max:6'],
            'days.*.shifts.*.start' => ['required', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'days.*.shifts.*.end' => ['required', 'string', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00)$/'],
        ];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return [
            'days.required' => 'Informe os dias da semana.',
            'days.array' => 'Dia da semana inválido.',
            'days.*.required' => 'Informe o atendimento do dia.',
            'days.*.array' => 'Informe o atendimento do dia.',
            'days.*.kind.required' => 'Escolha aberto, sob agendamento ou fechado.',
            'days.*.kind.enum' => 'Escolha aberto, sob agendamento ou fechado.',
            'days.*.shifts.array' => 'Informe os turnos do dia.',
            'days.*.shifts.max' => 'Informe até 6 turnos por dia.',
            'days.*.shifts.*.start.required' => 'Informe a hora de início no formato HH:MM, de 00:00 a 23:59.',
            'days.*.shifts.*.start.regex' => 'Informe a hora de início no formato HH:MM, de 00:00 a 23:59.',
            'days.*.shifts.*.end.required' => 'Informe a hora de término no formato HH:MM, até 24:00.',
            'days.*.shifts.*.end.regex' => 'Informe a hora de término no formato HH:MM, até 24:00.',
        ];
    }

    public static function empty(): static
    {
        $days = [];

        foreach (Weekday::cases() as $weekday) {
            $days[$weekday->value] = DaySchedule::closed();
        }

        return new self($days);
    }

    public static function default(): static
    {
        return self::empty();
    }

    /**
     * Lê um valor já gravado e validado. Dias ausentes valem fechado.
     *
     * @param  array<string, mixed>  $stored
     */
    public static function restore(array $stored): self
    {
        $days = self::empty()->days;

        foreach (Weekday::cases() as $weekday) {
            $day = $stored['days'][$weekday->value] ?? null;

            if (is_array($day)) {
                $days[$weekday->value] = DaySchedule::restore($day);
            }
        }

        return new self($days);
    }

    public function toArray(): array
    {
        return [
            'days' => array_map(fn (DaySchedule $day): array => $day->toArray(), $this->days),
        ];
    }

    /**
     * Completa o que não foi enviado com o valor atual da seção, dia a dia.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    private static function merge(array $input, array $current): array
    {
        $base = self::restore($current)->toArray()['days'];

        if (! array_key_exists('days', $input)) {
            return ['days' => $base];
        }

        if (! is_array($input['days'])) {
            return ['days' => $input['days']];
        }

        $days = $input['days'];

        foreach (Weekday::cases() as $weekday) {
            $key = $weekday->value;

            if (! array_key_exists($key, $days)) {
                $days[$key] = $base[$key];

                continue;
            }

            if (! is_array($days[$key])) {
                continue;
            }

            $kind = array_key_exists('kind', $days[$key]) ? $days[$key]['kind'] : $base[$key]['kind'];
            $kept = $kind === DayKind::Closed->value ? [] : $base[$key]['shifts'];

            $days[$key]['kind'] = $kind;
            $days[$key]['shifts'] = array_key_exists('shifts', $days[$key]) ? ($days[$key]['shifts'] ?? []) : $kept;
        }

        return ['days' => $days];
    }
}
