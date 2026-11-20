<?php

namespace App\Domains\Auditoria\Application\Queries;

use App\Domains\Auditoria\Domain\Models\AuditRecord;
use Illuminate\Database\Eloquent\Builder;

final class AuditQuery
{
    /** @return Builder<AuditRecord> */
    public function scoped(?int $accountId, bool $platform): Builder
    {
        $query = AuditRecord::query();

        if (! $platform) {
            abort_unless($accountId !== null, 403);
            $query->where('account_id', $accountId);
        }

        return $query;
    }

    /**
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
        ];

        foreach ($columns as $key => $column) {
            if (! empty($filters[$key])) {
                $query->where($column, $filters[$key]);
            }
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['until'])) {
            $query->whereDate('created_at', '<=', $filters['until']);
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
            'context' => $record->context,
            'represented_user_id' => $record->represented_user_id,
        ];
    }
}
