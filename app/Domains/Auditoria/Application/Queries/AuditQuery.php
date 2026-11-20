<?php

namespace App\Domains\Auditoria\Application\Queries;

use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class AuditQuery
{
    /** @return Builder<AuditRecord> */
    public function scoped(User $user, bool $platform): Builder
    {
        $q = AuditRecord::query();
        if (! $platform) {
            abort_unless($user->account_id !== null, 403);
            $q->where('account_id', $user->account_id);
        }

        return $q;
    }

    /**
     * @param  Builder<AuditRecord>  $q
     * @param  array<string,mixed>  $f
     * @return Builder<AuditRecord>
     */
    public function filter(Builder $q, array $f): Builder
    {
        foreach (['entity' => 'entity_type', 'event' => 'event', 'actor' => 'actor_id', 'account' => 'account_id'] as $key => $column) {
            if (! empty($f[$key])) {
                $q->where($column, $f[$key]);
            }
        }
        if (! empty($f['from'])) {
            $q->whereDate('created_at', '>=', $f['from']);
        }
        if (! empty($f['until'])) {
            $q->whereDate('created_at', '<=', $f['until']);
        }
        if (! empty($f['search'])) {
            $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f['search']).'%';
            $q->where(fn ($q) => $q->whereLike('entity_name', $needle)->orWhereLike('actor_name', $needle));
        }

        return $q;
    }

    /** @return array<string,mixed> */
    public function summary(AuditRecord $r): array
    {
        return ['id' => $r->id, 'name' => $r->entity_name, 'type' => config('audit.entities.'.$r->entity_type, $r->entity_type), 'event_key' => $r->event, 'event' => config('audit.events.'.$r->event, $r->event), 'actor' => $r->actor_name, 'created_at' => $r->created_at->toIso8601String(), 'account_id' => $r->account_id, 'context' => $r->context, 'represented_user_id' => $r->represented_user_id];
    }
}
