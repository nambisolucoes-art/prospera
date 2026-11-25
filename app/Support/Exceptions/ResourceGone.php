<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** O recurso existiu, mas não está mais disponível (convite vencido, por exemplo). Responde 410. */
final class ResourceGone extends HttpException
{
    public function __construct(string $message = '')
    {
        parent::__construct(410, $message);
    }
}
