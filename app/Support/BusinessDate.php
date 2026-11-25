<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Datas de calendário do negócio (`Y-m-d`), calculadas no fuso de negócio e nunca em `app.timezone` (UTC).
 * Até a S07 criar o fuso por estabelecimento, o fuso vem da configuração (PD2-08).
 */
final class BusinessDate
{
    public static function today(): string
    {
        return CarbonImmutable::now(config('prospera.business_timezone'))->toDateString();
    }

    public static function previousDay(string $date): string
    {
        return CarbonImmutable::parse($date)->subDay()->toDateString();
    }

    public static function nextDay(string $date): string
    {
        return CarbonImmutable::parse($date)->addDay()->toDateString();
    }
}
