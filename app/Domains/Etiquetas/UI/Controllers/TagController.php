<?php

declare(strict_types=1);

namespace App\Domains\Etiquetas\UI\Controllers;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Etiquetas\Application\Actions\Tags\CreateTagAction;
use App\Domains\Etiquetas\Application\Actions\Tags\DeleteTagAction;
use App\Domains\Etiquetas\Application\Actions\Tags\UpdateTagAction;
use App\Domains\Etiquetas\Application\Data\Tags\CreateTagData;
use App\Domains\Etiquetas\Application\Data\Tags\ListTagsData;
use App\Domains\Etiquetas\Application\Data\Tags\UpdateTagData;
use App\Domains\Etiquetas\Application\Queries\Tags\ListTagsQuery;
use App\Domains\Etiquetas\Application\Queries\Tags\ShowTagQuery;
use App\Support\ApiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TagController
{
    public function index(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        ListTagsQuery $query,
    ): JsonResponse|View {
        // Leitura do catálogo da conta: qualquer atribuição com a permissão (8.2.1), não só a de conta.
        $canList = ! $permissions->nodesFor($request->user(), 'account.tags.list')->isEmpty();

        abort_unless($canList, 403);

        return ApiPage::respond($this->listing($request, $account, $query, $canList));
    }

    public function preview(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        ShowTagQuery $query,
        int $tagId,
    ): JsonResponse {
        $canView = ! $permissions->nodesFor($request->user(), 'account.tags.view')->isEmpty();

        abort_unless($canView, 403);

        return response()->json($query->execute($account->requireId(), $tagId));
    }

    public function form(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        ListTagsQuery $list,
        ShowTagQuery $show,
        ?int $tagId = null,
    ): JsonResponse|View {
        $canList = ! $permissions->nodesFor($request->user(), 'account.tags.list')->isEmpty();

        return ApiPage::respond([
            ...$this->listing($request, $account, $list, $canList),
            'formOpen' => true,
            'tag' => $tagId === null ? null : $show->execute($account->requireId(), $tagId),
        ]);
    }

    public function store(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        CreateTagAction $action,
    ): RedirectResponse {
        $accountId = $account->requireId();

        $data = CreateTagData::fromInput($request->all(), $accountId);

        $action->execute($data, $accountId);
        ApiPage::toast('Etiqueta salva.');

        $canList = ! $permissions->nodesFor($request->user(), 'account.tags.list')->isEmpty();

        return redirect($account->path($canList ? 'etiquetas' : 'etiquetas/cadastrar'));
    }

    public function update(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        ShowTagQuery $query,
        UpdateTagAction $action,
        int $tagId,
    ): RedirectResponse {
        $tag = $query->execute($account->requireId(), $tagId);

        abort_if($tag->system_key !== null, 403);

        $data = UpdateTagData::fromInput($request->all(), $tag);

        $action->execute($data, $tag);
        ApiPage::toast('Etiqueta salva.');

        $canList = ! $permissions->nodesFor($request->user(), 'account.tags.list')->isEmpty();

        return redirect($account->path($canList ? 'etiquetas' : 'etiquetas/'.$tagId.'/editar'));
    }

    public function destroy(AccountContext $account, DeleteTagAction $action, int $tagId): RedirectResponse
    {
        $action->execute($account->requireId(), $tagId);
        ApiPage::toast('Etiqueta excluída.');

        return redirect($account->path('etiquetas'));
    }

    /** @return array<string,mixed> */
    private function listing(Request $request, AccountContext $account, ListTagsQuery $query, bool $canList): array
    {
        $filters = ListTagsData::fromInput($request->query());

        $tags = $query->execute(
            $account->requireId(),
            $filters,
            $canList,
            $canList ? $account->path('etiquetas') : $request->url(),
        );

        return [
            'tags' => $tags,
            'filters' => $filters->toArray(),
            'canList' => $canList,
            'formOpen' => false,
            'tag' => null,
        ];
    }
}
