<?php

declare(strict_types=1);

namespace App\Domains\Etiquetas\Domain\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

/** Etiquetas especiais pertencem ao sistema. Responde 403. */
final class SystemTagIsProtected extends AuthorizationException
{
    public function __construct()
    {
        parent::__construct('Etiquetas especiais são gerenciadas pelo sistema.');
    }
}
