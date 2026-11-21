<?php

namespace App\Domains\Auditoria\Application\Queries;

use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Acessos\Domain\DTOs\AuthorizedNodes;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class AuditQuery
{
    /** @return Builder<AuditRecord> */
    public function scoped(?int $accountId, bool $platform, ?AuthorizedNodes $nodes = null): Builder
    {
        $query = AuditRecord::query();

        // PD-02: a plataforma vê os próprios eventos e os administrativos das contas, nunca os de negócio.
        if ($platform) {
            $query->where(fn (Builder $events) => $events
                ->whereNull('account_id')
                ->orWhere('entity_type', 'account')
                ->orWhere('context', 'platform'));

            return $query;
        }

        abort_unless($accountId !== null, 403);
        $query->where('account_id', $accountId);
        ($nodes ?? new AuthorizedNodes)->apply($query);

        return $query;
    }

    /**
     * Os filtros só restringem o conjunto já autorizado por scoped().
     *
     * @param  Builder<AuditRecord>  $query
     * @param  array<string,mixed>  $filters
     * @return Builder<AuditRecord>
     */
    public function filter(Builder $query, array $filters): Builder
    {
        $columns = [
            'entity' => 'entity_type',
            'event' => 'event',
            'actor' => 'actor_id',
            'account' => 'account_id',
            'company' => 'company_id',
            'establishment' => 'establishment_id',
            'origin' => 'context',
        ];

        foreach ($columns as $key => $column) {
            if (! empty($filters[$key])) {
                $query->where($column, $filters[$key]);
            }
        }

        if (($filters['level'] ?? null) === 'tenant') {
            $query->whereNull('company_id');
        }

        if (($filters['level'] ?? null) === 'company') {
            $query->whereNotNull('company_id')->whereNull('establishment_id');
        }

        if (($filters['level'] ?? null) === 'establishment') {
            $query->whereNotNull('establishment_id');
        }

        // Intervalo semiaberto sobre a coluna, sem converter cada linha para data.
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (! empty($filters['until'])) {
            $query->where('created_at', '<', Carbon::parse($filters['until'])->addDay()->startOfDay());
        }

        if (! empty($filters['search'])) {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['search']);
            $needle = '%'.$escaped.'%';

            $query->where(fn ($search) => $search
                ->whereLike('entity_name', $needle)
                ->orWhereLike('actor_name', $needle));
        }

        return $query;
    }

    /**
     * Página da listagem com os nomes organizacionais em consultas de lote.
     *
     * @param  Builder<AuditRecord>  $query
     * @return Builder<AuditRecord>
     */
    public function page(Builder $query, bool $platform): Builder
    {
        $relations = [
            'company:id,name',
            'establishment:id,name',
        ];

        if ($platform) {
            $relations[] = 'account:id,name';
        }

        return $query
            ->with($relations)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    // Nó do evento para as permissões de visualização e de dados técnicos (seção 11.2).
    public function node(AuditRecord $record): AccessNode
    {
        if ($record->establishment_id !== null && $record->company_id !== null) {
            return AccessNode::establishment($record->account_id, $record->company_id, $record->establishment_id);
        }

        return $record->company_id === null
            ? AccessNode::tenant($record->account_id)
            : AccessNode::company($record->account_id, $record->company_id);
    }

    // O modo vem do início da mesma inspeção, na mesma conta; eventos anteriores à E08 não têm a referência.
    public function inspectionMode(AuditRecord $record): ?string
    {
        if ($record->inspection_id === null) {
            return null;
        }

        $started = AuditRecord::where('inspection_id', $record->inspection_id)
            ->where('account_id', $record->account_id)
            ->where('event', 'inspection-started')
            ->first(['changes']);
        $mode = $started?->changes['mode']['after'] ?? null;

        return is_string($mode) ? config('audit.values.account.mode.'.$mode) : null;
    }

    /** @return array<string,mixed> */
    public function summary(AuditRecord $record): array
    {
        return [
            'id' => $record->id,
            'name' => $record->entity_name,
            'type' => config('audit.entities.'.$record->entity_type, $record->entity_type),
            'event_key' => $record->event,
            'event' => config('audit.events.'.$record->event, $record->event),
            'actor' => $record->actor_name,
            'created_at' => $record->created_at->toIso8601String(),
            'account_id' => $record->account_id,
            'account' => $record->relationLoaded('account') ? $record->account?->name : null,
            'context' => $record->context,
            'origin' => config('audit.origins.'.$record->context, $record->context),
            'level' => $record->level(),
            'company' => $record->company?->name,
            'establishment' => $record->establishment?->name,
            'represented_user_id' => $record->represented_user_id,
            'represented' => $record->represented_user_name,
        ];
    }
}
