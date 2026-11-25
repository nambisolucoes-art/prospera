<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Domain\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

/** O operador não tem autoridade para criar a pessoa ou para alterar estes dados centrais. Responde 403. */
final class PersonChangeNotAllowed extends AuthorizationException {}
