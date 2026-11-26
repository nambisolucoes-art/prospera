<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Queries\Assignments;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Situação da lotação na data civil do estabelecimento dela (PD2-08, seção 10.3.1). Uma lotação só é vigente, futura
 * ou encerrada em relação ao "hoje" do fuso da própria unidade, então a condição é montada por fuso: cada ramo filtra
 * as unidades daquele fuso por subconsulta e compara `starts_on` e `ends_on` (datas de calendário, sem conversão)
 * com a data local. Não há junção, o que mantém o `FOR UPDATE` das Actions só nas lotações.
 */
final class LocalDatePredicate
{
    /** Já iniciada e ainda vigente no dia (inclui o último dia). */
    public const CURRENT = 'current';

    public const FUTURE = 'future';

    public const ENDED = 'ended';

    /** Já iniciada e com fim posterior ao dia: o que a inativação do colaborador ainda precisa encerrar. */
    public const OPEN = 'open';

    /**
     * @param  Builder|EloquentBuilder<*>  $query
     * @param  array<string, string>  $todays  fuso IANA para a data civil de hoje nele
     */
    public static function apply(Builder|EloquentBuilder $query, string $status, array $todays, string $prefix = ''): void
    {
        $query->where(function ($any) use ($status, $todays, $prefix): void {
            if ($todays === []) {
                $any->whereRaw('1 = 0');

                return;
            }

            foreach ($todays as $zone => $today) {
                $any->orWhere(function ($unit) use ($status, $zone, $today, $prefix): void {
                    $unit->whereIn($prefix.'establishment_id', DB::table('establishments')
                        ->where('timezone', $zone)
                        ->select('id'));

                    self::period($unit, $status, $today, $prefix);
                });
            }
        });
    }

    private static function period(mixed $query, string $status, string $today, string $prefix): void
    {
        $starts = $prefix.'starts_on';
        $ends = $prefix.'ends_on';

        match ($status) {
            self::CURRENT => $query->where($starts, '<=', $today)
                ->where(fn ($period) => $period->whereNull($ends)->orWhere($ends, '>=', $today)),
            self::FUTURE => $query->where($starts, '>', $today),
            self::ENDED => $query->where($ends, '<', $today),
            self::OPEN => $query->where($starts, '<=', $today)
                ->where(fn ($period) => $period->whereNull($ends)->orWhere($ends, '>', $today)),
            default => throw new \InvalidArgumentException('Situação de lotação desconhecida: '.$status),
        };
    }
}
