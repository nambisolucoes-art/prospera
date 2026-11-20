<?php

namespace App\Http\Middleware;

use App\Domains\Acessos\Application\Context\OrganizationContext;
use App\Domains\Organizacao\Application\Data\WorkspaceSelectionData;
use App\Support\ApiPage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aplicado só aos grupos /conta/empresa/{empresa} e /conta/estabelecimento/{estabelecimento} (E06).
 *
 * Uso: organization:establishment, organization:establishment,consolidated ou organization:company,history.
 * "consolidated" declara que o módulo aceita o código reservado "todos"; "history" aceita leitura
 * em empresa ou unidade inativa. Roda depois de auth e de EnforceAccountInspection.
 */
final readonly class ResolveOrganizationContext
{
    public function __construct(private OrganizationContext $context) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $level, string ...$flags): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        // A abertura direta só entrega o HTML inicial, sem dados. A aplicação pede a página em JSON,
        // passa por esta validação e mostra a orientação quando a unidade não está disponível.
        if ($request->isMethodSafe() && ! $request->expectsJson()) {
            $shell = ApiPage::respond();

            return $shell instanceof Response ? $shell : response($shell);
        }

        $consolidated = in_array('consolidated', $flags, true);
        $history = in_array('history', $flags, true);
        $reading = $request->isMethodSafe();

        if ($level === 'company') {
            $code = (string) $request->route('empresa');

            abort_if($code === OrganizationContext::CONSOLIDATED, 404);
            $this->context->enterCompany($user, $code, $history, $reading);
        } else {
            $code = (string) $request->route('estabelecimento');

            if ($code === OrganizationContext::CONSOLIDATED) {
                abort_unless($consolidated, 404);

                // Escritas sempre nomeiam uma unidade; o consolidado é só consulta.
                abort_unless($reading, 403, 'Escolha um estabelecimento para concluir esta operação.');
                $this->context->enterConsolidated($user, WorkspaceSelectionData::fromQuery($request->query()));
            } else {
                $this->context->enterEstablishment($user, $code, $history, $reading);
            }

            if ($consolidated) {
                $this->context->allowConsolidated();
            }
        }

        // Os controllers recebem o contexto pelo serviço, não pelo parâmetro bruto da rota.
        $request->route()?->forgetParameter('empresa');
        $request->route()?->forgetParameter('estabelecimento');

        return $next($request);
    }
}
