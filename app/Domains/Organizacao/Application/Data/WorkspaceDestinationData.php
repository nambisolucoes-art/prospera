<?php

namespace App\Domains\Organizacao\Application\Data;

use App\Domains\Acessos\Application\Context\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * Destino de "?para=modulo" na entrada da área de trabalho (E06).
 *
 * Só aceita um caminho relativo que corresponda a uma rota GET registrada no grupo
 * /conta/estabelecimento/{estabelecimento} com o middleware de contexto. Assim, nenhum endereço
 * externo, arbitrário ou de escrita vira destino. Um destino desconhecido responde 404.
 */
final readonly class WorkspaceDestinationData
{
    private const PATTERN = '/^[a-z0-9-]+(?:\/[a-z0-9-]+)*$/';

    private function __construct(
        public string $path,
        public bool $consolidated,
    ) {}

    /** @param array<string, mixed> $query */
    public static function fromQuery(array $query, Router $router): ?self
    {
        if (! array_key_exists('para', $query)) {
            return null;
        }

        $path = $query['para'];
        abort_unless(is_string($path) && preg_match(self::PATTERN, $path) === 1, 404);

        // A unidade da sondagem é o código reservado: só o formato da URL importa aqui.
        $probe = Request::create('/contas/sondagem/estabelecimento/'.OrganizationContext::CONSOLIDATED.'/'.$path);
        $route = collect($router->getRoutes()->get('GET'))
            ->first(fn (Route $route) => $route->matches($probe));

        $middleware = $route === null ? [] : $route->gatherMiddleware();
        $context = collect($middleware)
            ->first(fn ($name) => is_string($name) && str_starts_with($name, 'organization:establishment'));
        abort_if($context === null, 404);

        $flags = explode(',', substr($context, strlen('organization:')));

        return new self($path, in_array('consolidated', $flags, true));
    }
}
