<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Domain\Enums;

/** Estado lido por `ProfileResolver`: o editor e a prévia leem `draft`, o público e o diretório leem `published`. */
enum ProfileState: string
{
    case Draft = 'draft';
    case Published = 'published';
}
