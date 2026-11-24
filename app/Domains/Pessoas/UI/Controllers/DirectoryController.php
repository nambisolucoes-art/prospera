<?php

namespace App\Domains\Pessoas\UI\Controllers;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Etiquetas\Application\Actions\DeleteTagAction;
use App\Domains\Etiquetas\Application\Actions\SaveTagAction;
use App\Domains\Etiquetas\Application\Data\SaveTagData;
use App\Domains\Etiquetas\Application\Data\TagFiltersData;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Pessoas\Application\Queries\PeopleDirectoryQuery;
use App\Support\ApiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DirectoryController
{
    public function deleteTag(DeleteTagAction $delete, int $tagId): RedirectResponse
    {
        $delete->execute(app(AccountContext::class)->requireId(), $tagId);
        ApiPage::toast('Etiqueta excluída.');

        return redirect(app(AccountContext::class)->path('etiquetas'));
    }

    public function tags(Request $request, PeopleDirectoryQuery $directory): JsonResponse|View
    {
        // Leitura do catálogo da conta: qualquer atribuição com a permissão (8.2.1), não só a de conta.
        $listable = ! app(EffectivePermissions::class)->nodesFor($request->user(), 'account.tags.list')->isEmpty();

        abort_unless($listable, 403);

        return ApiPage::respond($this->tagsPayload($request, $directory));
    }

    /** @return array<string,mixed> */
    private function tagsPayload(Request $request, PeopleDirectoryQuery $directory): array
    {
        $canList = ! app(EffectivePermissions::class)->nodesFor($request->user(), 'account.tags.list')->isEmpty();
        $filters = TagFiltersData::fromInput($request->query());

        $tags = $directory->tagList(
            app(AccountContext::class)->requireId(),
            $filters,
            $canList,
            $canList ? app(AccountContext::class)->path('etiquetas') : $request->url(),
        );

        return [
            'tags' => $tags,
            'filters' => $filters->toArray(),
            'canList' => $canList,
            'formOpen' => false,
            'tag' => null,
        ];
    }

    public function previewTag(Request $request, PeopleDirectoryQuery $directory, int $tagId): JsonResponse
    {
        $viewable = ! app(EffectivePermissions::class)->nodesFor($request->user(), 'account.tags.view')->isEmpty();

        abort_unless($viewable, 403);

        return response()->json($directory->tags(app(AccountContext::class)->requireId())->findOrFail($tagId));
    }

    public function tagForm(Request $request, ?int $tagId = null): JsonResponse|View
    {
        $tag = $tagId
            ? Tag::where('account_id', app(AccountContext::class)->requireId())->findOrFail($tagId)
            : null;

        return ApiPage::respond([
            ...$this->tagsPayload($request, app(PeopleDirectoryQuery::class)),
            'formOpen' => true,
            'tag' => $tag,
        ]);
    }

    public function saveTag(Request $request, SaveTagAction $save, ?int $tagId = null): RedirectResponse
    {
        $accountId = app(AccountContext::class)->requireId();
        $tag = $tagId ? Tag::where('account_id', $accountId)->findOrFail($tagId) : null;

        abort_if($tag?->system_key !== null, 403);

        $data = SaveTagData::fromInput($request->all(), $accountId, $tagId, $tag?->module);

        $save->execute($accountId, $tag, $data);
        ApiPage::toast('Etiqueta salva.');

        $listable = ! app(EffectivePermissions::class)->nodesFor($request->user(), 'account.tags.list')->isEmpty();

        if ($listable) {
            return redirect(app(AccountContext::class)->path('etiquetas'));
        }

        return redirect(app(AccountContext::class)->path($tagId ? 'etiquetas/'.$tagId.'/editar' : 'etiquetas/cadastrar'));
    }
}
