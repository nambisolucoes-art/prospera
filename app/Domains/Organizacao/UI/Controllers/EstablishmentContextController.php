<?php

namespace App\Domains\Organizacao\UI\Controllers;

use App\Domains\Acessos\Application\Context\OrganizationContext;
use App\Domains\Organizacao\Application\Actions\SaveWorkspacePreferenceAction;
use App\Domains\Organizacao\Application\Data\WorkspaceSelectionData;
use App\Domains\Organizacao\Domain\DTOs\NavigableEstablishment;
use App\Domains\Plataforma\Infrastructure\Session\AccountInspectionSession;
use App\Support\ApiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

final class EstablishmentContextController
{
    // A entrada sem código abre o padrão revalidado e fixa a seleção na URL.
    public function enter(Request $request, OrganizationContext $context): RedirectResponse
    {
        $codes = $context->defaultCodes($request->user());
        abort_if($codes === [], 403, 'Você não tem acesso a nenhum estabelecimento ativo desta conta.');

        $path = $request->query('para', '');
        $suffix = is_string($path) && preg_match('/^[a-z0-9-]+(?:\/[a-z0-9-]+)*$/', $path) === 1 ? '/'.$path : '';

        if (count($codes) === 1) {
            return redirect('/conta/estabelecimento/'.$codes[0].$suffix);
        }

        return redirect('/conta/estabelecimento/todos'.$suffix.'?unidades='.implode(',', $codes));
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

    public function home(Request $request, OrganizationContext $context): JsonResponse|View
    {
        if ($context->mode() === 'consolidated') {
            $selected = array_flip($context->selectedCodes());
            $units = array_filter(
                $context->navigable($request->user()),
                fn (NavigableEstablishment $unit) => isset($selected[$unit->code]),
            );

            return ApiPage::respond([
                'workspace' => array_values(array_map(fn (NavigableEstablishment $unit) => $unit->toArray(), $units)),
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
