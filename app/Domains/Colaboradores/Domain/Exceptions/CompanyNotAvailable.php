<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Domain\Exceptions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * A empresa escolhida não existe para o acesso da pessoa. Responde 404, sem revelar a empresa.
 *
 * @extends ModelNotFoundException<Model>
 */
final class CompanyNotAvailable extends ModelNotFoundException {}
