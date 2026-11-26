<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Datas de calendário do negócio (`Y-m-d`), sempre calculadas no fuso IANA do estabelecimento envolvido (PD2-08) e
 * nunca em `app.timezone` (UTC). Datas de calendário não viram instantes: a aritmética de dias usa o calendário puro,
 * e só o início do dia civil seguinte passa pela base de fusos, para respeitar dias de 23 e 25 horas.
 */
final class BusinessDate
{
    /** Data civil de hoje no fuso informado. O relógio de teste do Carbon vale, então o cálculo é reproduzível. */
    public static function todayIn(string $timezone): string
    {
        return CarbonImmutable::now($timezone)->toDateString();
    }

    /** Data civil de um instante no fuso informado. */
    public static function dateOf(CarbonImmutable $instant, string $timezone): string
    {
        return $instant->setTimezone($timezone)->toDateString();
    }

    public static function previousDay(string $date): string
    {
        return self::calendar($date)->subDay()->toDateString();
    }

    public static function nextDay(string $date): string
    {
        return self::calendar($date)->addDay()->toDateString();
    }

    /**
     * O primeiro instante do dia civil seguinte à data, no fuso. É o `valid_until` das respostas que dependem de data
     * (seção 09.7): nunca "agora mais 24 horas", porque o dia local pode ter 23 ou 25 horas. Se a meia-noite não
     * existir no fuso (mudança de horário à meia-noite), vale o primeiro instante que existe naquela data.
     */
    public static function startOfNextDay(string $date, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::parse(self::nextDay($date).' 00:00:00', $timezone);
    }

    private static function calendar(string $date): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $date, 'UTC');
    }
}
