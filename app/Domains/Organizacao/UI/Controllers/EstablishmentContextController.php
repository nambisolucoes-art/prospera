<?php

namespace App\Domains\Organizacao\UI\Controllers;

use App\Domains\Acessos\Application\Context\OrganizationContext;
use App\Domains\Organizacao\Application\Actions\SaveWorkspacePreferenceAction;
use App\Domains\Organizacao\Application\Data\WorkspaceDestinationData;
use App\Domains\Organizacao\Application\Data\WorkspaceSelectionData;
use App\Domains\Organizacao\Domain\DTOs\NavigableEstablishment;
use App\Domains\Plataforma\Infrastructure\Session\AccountInspectionSession;
use App\Support\ApiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

final class EstablishmentContextController
{
    /**
     * Entrada da área de trabalho: usa a seleção enviada pela aba ou o padrão revalidado e fixa o destino na URL.
     *
     * Com várias unidades, um destino sem consolidado não recebe uma unidade escolhida pelo sistema:
     * a área de trabalho pede a escolha do estabelecimento.
     */
    public function enter(Request $request, OrganizationContext $context, Router $router): RedirectResponse
    {
        $user = $request->user();
        $selection = WorkspaceSelectionData::fromQuery($request->query());
        $destination = WorkspaceDestinationData::fromQuery($request->query(), $router);

        $codes = $context->workspaceCodes($user, $selection);
        abort_if($codes === [], 403, 'Você não tem acesso a nenhum estabelecimento ativo desta conta.');

        $base = '/conta/estabelecimento/';
        $units = implode(',', $codes);
        $suffix = $destination === null ? '' : '/'.$destination->path;

        if (count($codes) === 1) {
            return redirect($base.$codes[0].$suffix);
        }

        if ($destination === null || $destination->consolidated) {
            return redirect($base.OrganizationContext::CONSOLIDATED.$suffix.'?unidades='.$units);
        }

        return redirect($base.OrganizationContext::CONSOLIDATED.'?unidades='.$units.'&para='.$destination->path);
    }

    public function showDefault(Request $request, OrganizationContext $context): JsonResponse
    {
        $context->accountId();

        return response()->json(
            ['codes' => $context->defaultCodes($request->user())],
            headers: ['Cache-Control' => 'private, no-store'],
        );
    }

    public function saveDefault(Request $request, SaveWorkspacePreferenceAction $save): Response
    {
        abort_if(AccountInspectionSession::current($request->session()) !== null, 403);

        $save->execute($request->user(), WorkspaceSelectionData::fromInput($request->all()));

        return response()->noContent();
    }

    public function home(Request $request, OrganizationContext $context, Router $router): JsonResponse|View
    {
        if ($context->mode() === 'consolidated') {
            $selected = array_flip($context->selectedCodes());
            $units = array_filter(
                $context->navigable($request->user()),
                fn (NavigableEstablishment $unit) => isset($selected[$unit->code]),
            );

            // Destino que opera numa unidade por vez: a tela lista as unidades para a escolha.
            $destination = WorkspaceDestinationData::fromQuery($request->query(), $router);

            return ApiPage::respond([
                'workspace' => array_values(array_map(fn (NavigableEstablishment $unit) => $unit->toArray(), $units)),
                'destination' => $destination?->path,
            ]);
        }

        $unit = $context->establishment();
        abort_if($unit === null, 404);

        $user = $request->user();
        $node = $context->node();

        return ApiPage::respond([
            'establishment' => [
                ...$unit->toArray(),
                'id' => $unit->id,
                'can_view' => $user->can('account.establishments.view', $node),
                'can_update' => $user->can('account.establishments.update', $node),
            ],
        ]);
    }
}
