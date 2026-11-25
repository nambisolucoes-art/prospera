<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** Outra alteração concorrente impediu a operação. Responde 409. */
final class ConflictingChange extends HttpException
{
    public function __construct(string $message = '')
    {
        parent::__construct(409, $message);
    }
}
