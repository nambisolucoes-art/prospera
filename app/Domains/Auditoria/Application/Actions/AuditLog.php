<?php

namespace App\Domains\Auditoria\Application\Actions;

use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Etiquetas\Domain\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class AuditLog
{
    /**
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        $context = app(AuditContext::class);
        $owned = $context->operationId === null;
        $context->operationId ??= (string) Str::uuid();
        try {
            return DB::transaction(fn () => $callback());
        } finally {
            if ($owned) {
                $context->operationId = null;
            }
        }
    }

    /** @param array<string,array{label:string,before:mixed,after:mixed}> $changes */
    public static function record(string $type, int $id, ?int $accountId, string $event, string $label, array $changes = []): void
    {
        if (! Schema::hasTable('audit_records')) {
            // Earlier installation migrations predate the central history table.
            if (app()->runningInConsole()) {
                return;
            }
            throw new \LogicException('A tabela de auditoria não está disponível. Execute as migrações.');
        }
        $context = app(AuditContext::class);
        $actor = auth()->user();
        $operationId = $context->operationId ?? (string) Str::uuid();
        $query = AuditRecord::where('operation_id', $operationId)->where('entity_type', $type)->where('entity_id', $id)->where('event', $event);
        if (in_array($event, ['created', 'updated'], true)) {
            $query = AuditRecord::where('operation_id', $operationId)->where('entity_type', $type)->where('entity_id', $id)->whereIn('event', ['created', 'updated']);
        }
        $existing = $query->first();
        $merged = $existing ? $existing->changes : [];
        foreach ($changes as $field => $change) {
            if (isset($merged[$field])) {
                $change['before'] = $merged[$field]['before'];
            }$merged[$field] = $change;
        }
        if (($existing ? $existing->event : $event) === 'updated') {
            $merged = array_filter($merged, fn ($change) => $change['before'] !== $change['after']);
        }
        if ($event === 'updated' && ! $merged) {
            $existing?->delete();

            return;
        }
        $values = ['operation_id' => $operationId, 'actor_id' => $context->actorId ?? $actor?->id, 'actor_name' => $context->actorId !== null ? $context->actorName : ($actor ? $actor->name : 'Sistema'), 'account_id' => $accountId, 'context' => $context->context === 'system' && $actor ? ($actor->is_saas_admin ? 'platform' : 'account') : $context->context, 'represented_user_id' => $context->representedUserId, 'entity_type' => $type, 'entity_id' => $id, 'entity_name' => $label, 'event' => $existing ? $existing->event : $event, 'changes' => $merged, 'created_at' => $existing ? $existing->created_at : now()];
        if ($existing) {
            $existing->fill($values)->saveOrFail();
        } else {
            AuditRecord::create($values);
        }
    }

    /**
     * @param  array<int,mixed>  $before
     * @param  array<int,mixed>  $after
     */
    public static function relationship(Model&Auditable $model, string $field, array $before, array $after, string $label): void
    {
        $before = array_map('intval', $before);
        $after = array_map('intval', $after);
        sort($before);
        sort($after);
        if ($before === $after) {
            return;
        }
        $names = function (array $ids) use ($field, $model): array {
            $query = $field === 'permission_ids' ? Permission::query() : Tag::where('account_id', $model->auditAccountId());
            $labels = $query->whereIn('id', $ids)->pluck('name', 'id');

            return array_map(fn ($id) => $labels[$id] ?? 'Registro #'.$id, $ids);
        };
        self::record($model::auditType(), (int) $model->getKey(), $model->auditAccountId(), 'updated', (string) ($model->getAttribute('name') ?? '#'.$model->getKey()), [$field => ['label' => $label, 'before' => $names($before), 'after' => $names($after)]]);
    }
}
