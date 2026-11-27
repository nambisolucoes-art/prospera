<?php

declare(strict_types=1);

namespace App\Domains\Midia\Domain\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** A mídia está em uso e não pode ser removida agora. Responde 409. */
final class MediaInUse extends HttpException
{
    public function __construct(string $message = 'Esta mídia está em uso e não pode ser removida agora.')
    {
        parent::__construct(409, $message);
    }
}
