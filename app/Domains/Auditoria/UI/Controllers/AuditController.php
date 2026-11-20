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
    public function index(Request $r, AuditQuery $query): JsonResponse|View
    {
        $platform = $r->is('saas/*');
        $user = $r->user();
        $f = AuditFilterData::fromInput($r->query(), $platform)->values;
        $scope = $query->scoped($user, $platform);
        $actors = (clone $scope)->whereNotNull('actor_id')->select('actor_id', 'actor_name')->distinct()->orderBy('actor_name')->get()->map(fn ($r) => ['value' => (string) $r->actor_id, 'label' => $r->actor_name])->unique('value')->values();
        $entities = (clone $scope)->distinct()->pluck('entity_type')->map(fn ($v) => ['value' => $v, 'label' => config('audit.entities.'.$v, $v)])->sortBy('label')->values();
        $events = (clone $scope)->distinct()->pluck('event')->map(fn ($v) => ['value' => $v, 'label' => config('audit.events.'.$v, $v)])->sortBy('label')->values();

        return ApiPage::respond(['records' => $query->filter($scope, $f)->orderByDesc('created_at')->orderByDesc('id')->paginate(PageSize::fromInput($r->query('per_page')))->withQueryString()->through(fn ($v) => $query->summary($v)), 'filters' => $f, 'options' => ['actors' => $actors, 'entities' => $entities, 'events' => $events, 'accounts' => $platform ? Client::orderBy('name')->get(['id', 'name'])->map(fn ($v) => ['value' => (string) $v->id, 'label' => $v->name]) : []], 'platform' => $platform]);
    }

    public function show(Request $r, AuditQuery $query, int $recordId, AuditValueFormatter $formatter): JsonResponse
    {
        $platform = $r->is('saas/*');
        $record = $query->scoped($r->user(), $platform)->findOrFail($recordId);
        $technical = $platform ? $r->user()->can('platform.audit.technical') : $r->user()->can('account.audit.technical');

        return response()->json([...$query->summary($record), 'changes' => $formatter->changes($record), 'technical' => $technical ? $record->only(['operation_id', 'entity_type', 'entity_id', 'actor_id', 'account_id', 'context', 'represented_user_id']) : null], headers: ['Cache-Control' => 'private, no-store']);
    }
}
