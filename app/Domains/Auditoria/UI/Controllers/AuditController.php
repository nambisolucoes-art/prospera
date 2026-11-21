<?php

namespace App\Domains\Auditoria\UI\Controllers;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Auditoria\Application\Data\AuditFilterData;
use App\Domains\Auditoria\Application\Queries\AuditFilterOptionsQuery;
use App\Domains\Auditoria\Application\Queries\AuditQuery;
use App\Domains\Auditoria\Application\Queries\AuditValueFormatter;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Support\ApiPage;
use App\Support\PageSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AuditController
{
    public function index(
        Request $request,
        AuditQuery $query,
        AuditFilterOptionsQuery $options,
        EffectivePermissions $permissions,
    ): JsonResponse|View {
        $platform = $request->is('saas/*');
        $filters = AuditFilterData::fromInput($request->query(), $platform)->values;
        $accountId = $platform ? null : app(AccountContext::class)->requireId();
        $nodes = $platform ? null : $permissions->nodesFor($request->user(), 'account.audit.list');
        abort_if($nodes?->isEmpty(), 403);

        $scope = $query->scoped($accountId, $platform, $nodes);

        // As opções de filtro usam a mesma consulta com escopo da listagem.
        $filterOptions = $options->options($scope, $accountId, $nodes);

        $records = $query->page($query->filter($scope, $filters), $platform)
            ->paginate(PageSize::fromInput($request->query('per_page')))
            ->withQueryString()
            ->through(fn (AuditRecord $record) => [
                ...$query->summary($record),
                'can_view' => $platform
                    ? $request->user()->can('platform.audit.view')
                    : $request->user()->can('account.audit.view', $query->node($record)),
            ]);

        return ApiPage::respond([
            'records' => $records,
            'filters' => $filters,
            'options' => $filterOptions,
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

        $record = $scope
            ->with(['account:id,name', 'company:id,name', 'establishment:id,name'])
            ->findOrFail($recordId);

        $technical = $platform
            ? $user->can('platform.audit.technical')
            : $user->can('account.audit.technical', $query->node($record));

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
                'inspection_id',
                'company_id',
                'establishment_id',
            ])
            : null;

        return response()->json(
            [
                ...$query->summary($record),
                'account' => $record->account?->name,
                'inspection_mode' => $query->inspectionMode($record),
                'changes' => $formatter->changes($record),
                'technical' => $technicalData,
            ],
            headers: ['Cache-Control' => 'private, no-store'],
        );
    }
}
