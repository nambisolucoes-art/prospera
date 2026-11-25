<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * O recurso não existe para o acesso do operador, sem revelar se existe em outro escopo. Responde 404.
 *
 * @extends ModelNotFoundException<Model>
 */
final class ResourceNotAvailable extends ModelNotFoundException {}
