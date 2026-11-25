<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

/** A operação é negada ao operador. Responde 403. */
final class OperationNotAllowed extends AuthorizationException {}
