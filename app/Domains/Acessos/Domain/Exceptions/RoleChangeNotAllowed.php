<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Domain\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

/** A alteração do cargo é negada: cargo protegido ou permissões que o operador não possui. Responde 403. */
final class RoleChangeNotAllowed extends AuthorizationException
{
    public static function protectedRole(): self
    {
        return new self('O cargo de administração inicial é protegido.');
    }

    public static function permissionsNotHeld(): self
    {
        return new self('Você não pode conceder permissões que não possui.');
    }

    public static function assignmentsOutOfReach(): self
    {
        return new self('Você não pode remover atribuições de um cargo com permissões que não possui.');
    }
}
