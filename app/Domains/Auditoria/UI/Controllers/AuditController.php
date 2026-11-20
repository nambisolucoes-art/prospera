<?php

namespace App\Domains\Auditoria\UI\Controllers;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Auditoria\Application\Data\AuditFilterData;
use App\Domains\Auditoria\Application\Queries\AuditQuery;
use App\Domains\Auditoria\Application\Queries\AuditValueFormatter;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Support\ApiPage;
use App\Support\PageSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AuditController
{
    public function index(Request $request, AuditQuery $query, EffectivePermissions $permissions): JsonResponse|View
    {
        $platform = $request->is('saas/*');
        $filters = AuditFilterData::fromInput($request->query(), $platform)->values;
        $accountId = $platform ? null : app(AccountContext::class)->requireId();
        $nodes = $platform ? null : $permissions->nodesFor($request->user(), 'account.audit.list');
        abort_if($nodes?->isEmpty(), 403);

        $scope = $query->scoped($accountId, $platform, $nodes);

        // As opções de filtro usam a mesma consulta com escopo da listagem.
        $actors = (clone $scope)
            ->whereNotNull('actor_id')
            ->select('actor_id', 'actor_name')
            ->distinct()
            ->orderBy('actor_name')
            ->get()
            ->map(fn ($record) => ['value' => (string) $record->actor_id, 'label' => $record->actor_name])
            ->unique('value')
            ->values();

        $entities = (clone $scope)
            ->distinct()
            ->pluck('entity_type')
            ->map(fn ($type) => ['value' => $type, 'label' => config('audit.entities.'.$type, $type)])
            ->sortBy('label')
            ->values();

        $events = (clone $scope)
            ->distinct()
            ->pluck('event')
            ->map(fn ($event) => ['value' => $event, 'label' => config('audit.events.'.$event, $event)])
            ->sortBy('label')
            ->values();

        $accounts = $platform
            ? Client::orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($account) => ['value' => (string) $account->id, 'label' => $account->name])
            : [];

        $records = $query->filter($scope, $filters)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(PageSize::fromInput($request->query('per_page')))
            ->withQueryString()
            ->through(fn (AuditRecord $record) => [
                ...$query->summary($record),
                'can_view' => $platform
                    ? $request->user()->can('platform.audit.view')
                    : $request->user()->can('account.audit.view', $this->node($record)),
            ]);

        return ApiPage::respond([
            'records' => $records,
            'filters' => $filters,
            'options' => [
                'actors' => $actors,
                'entities' => $entities,
                'events' => $events,
                'accounts' => $accounts,
            ],
            'platform' => $platform,
        ]);
    }

    public function show(
        Request $request,
        AuditQuery $query,
        int $recordId,
        AuditValueFormatter $formatter,
        EffectivePermissions $permissions,
    ): JsonResponse {
        $platform = $request->is('saas/*');
        $accountId = $platform ? null : app(AccountContext::class)->requireId();
        $user = $request->user();
        $nodes = $platform ? null : $permissions->nodesFor($user, 'account.audit.list');
        abort_if($nodes?->isEmpty(), 403);

        $scope = $query->scoped($accountId, $platform, $nodes);

        if (! $platform) {
            $view = $permissions->nodesFor($user, 'account.audit.view');
            abort_if($view->isEmpty(), 403);
            $view->apply($scope);
        }

        $record = $scope->findOrFail($recordId);
        $technical = $platform
            ? $user->can('platform.audit.technical')
            : $user->can('account.audit.technical', $this->node($record));

        $technicalData = $technical
            ? $record->only([
                'operation_id',
                'entity_type',
                'entity_id',
                'actor_id',
                'account_id',
                'context',
                'represented_user_id',
                'membership_id',
                'company_id',
                'establishment_id',
            ])
            : null;

        return response()->json(
            [
                ...$query->summary($record),
                'changes' => $formatter->changes($record),
                'technical' => $technicalData,
            ],
            headers: ['Cache-Control' => 'private, no-store'],
        );
    }

    private function node(AuditRecord $record): AccessNode
    {
        if ($record->establishment_id !== null && $record->company_id !== null) {
            return AccessNode::establishment($record->account_id, $record->company_id, $record->establishment_id);
        }

        return $record->company_id === null
            ? AccessNode::tenant($record->account_id)
            : AccessNode::company($record->account_id, $record->company_id);
    }
}
