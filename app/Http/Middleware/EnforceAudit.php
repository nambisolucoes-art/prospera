<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Auditoria\Application\Actions\AuditContext;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Plataforma\Infrastructure\Session\AccountInspectionSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforceAudit
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(AuditContext::class);
        $context->begin($request);
        try {
            if ($request->isMethodSafe()) {
                return $next($request);
            }

            return AuditLog::transaction(function () use ($request, $next) {
                $inspection = $request->routeIs('logout') && $request->hasSession()
                    ? AccountInspectionSession::current($request->session())
                    : null;

                if ($inspection !== null) {
                    $accountName = Client::whereKey($inspection->accountId)->value('name');

                    AuditLog::record(
                        'account',
                        $inspection->accountId,
                        $inspection->accountId,
                        'inspection-ended',
                        $accountName ?? 'Conta #'.$inspection->accountId,
                        ['ending' => ['label' => 'Encerramento', 'before' => null, 'after' => 'Saída da sessão']],
                    );
                }

                $response = $next($request);

                // Respostas de erro desfazem a transação e o histórico gravado nela.
                if (
                    $response->getStatusCode() >= 400
                    || (property_exists($response, 'exception') && $response->exception instanceof \Throwable)
                ) {
                    throw new AuditResponseRollback($response);
                }

                if ($request->routeIs('passkey.store', 'passkey.destroy') && $request->user()) {
                    $user = $request->user();
                    $after = $request->routeIs('passkey.store') ? 'Chave cadastrada' : 'Chave excluída';

                    AuditLog::record(
                        'user',
                        $user->id,
                        app(AccountContext::class)->linkedId($user) ?? $user->auditAccountId(),
                        'updated',
                        $user->name,
                        ['passkeys' => ['label' => 'Chaves de acesso', 'before' => null, 'after' => $after]],
                    );
                }

                return $response;
            });
        } catch (AuditResponseRollback $rollback) {
            return $rollback->response;
        } finally {
            $context->clear();
        }
    }
}
final class AuditResponseRollback extends \RuntimeException
{
    public function __construct(public readonly Response $response)
    {
        parent::__construct('Operação revertida.');
    }
}
