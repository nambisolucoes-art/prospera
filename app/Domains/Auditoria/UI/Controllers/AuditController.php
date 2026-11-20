<?php

namespace App\Domains\Auditoria\UI\Controllers;

use App\Domains\Auditoria\Application\Data\AuditFilterData;
use App\Domains\Auditoria\Application\Queries\AuditQuery;
use App\Domains\Auditoria\Application\Queries\AuditValueFormatter;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Support\ApiPage;
use App\Support\PageSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AuditController
{
    public function index(Request $request, AuditQuery $query): JsonResponse|View
    {
        $platform = $request->is('saas/*');
        $filters = AuditFilterData::fromInput($request->query(), $platform)->values;
        $scope = $query->scoped($request->user(), $platform);

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
            ->through(fn ($record) => $query->summary($record));

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
    ): JsonResponse {
        $platform = $request->is('saas/*');
        $record = $query->scoped($request->user(), $platform)->findOrFail($recordId);

        $technical = $platform
            ? $request->user()->can('platform.audit.technical')
            : $request->user()->can('account.audit.technical');

        $technicalData = $technical
            ? $record->only([
                'operation_id',
                'entity_type',
                'entity_id',
                'actor_id',
                'account_id',
                'context',
                'represented_user_id',
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
}
