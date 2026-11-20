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
    public static function record(
        string $type,
        int $id,
        ?int $accountId,
        string $event,
        string $label,
        array $changes = [],
        ?int $companyId = null,
        ?int $establishmentId = null,
    ): void {
        if (! Schema::hasTable('audit_records')) {
            // Migrations antigas da instalação são anteriores à tabela central de histórico.
            if (app()->runningInConsole()) {
                return;
            }

            throw new \LogicException('A tabela de auditoria não está disponível. Execute as migrações.');
        }

        $context = app(AuditContext::class);
        $actor = auth()->user();
        $operationId = $context->operationId ?? (string) Str::uuid();

        // Criação e edição do mesmo registro na mesma operação viram um único evento.
        $events = in_array($event, ['created', 'updated'], true) ? ['created', 'updated'] : [$event];

        $existing = AuditRecord::where('operation_id', $operationId)
            ->where('entity_type', $type)
            ->where('entity_id', $id)
            ->whereIn('event', $events)
            ->first();

        $merged = $existing ? $existing->changes : [];

        foreach ($changes as $field => $change) {
            if (isset($merged[$field])) {
                $change['before'] = $merged[$field]['before'];
            }

            $merged[$field] = $change;
        }

        $recordedEvent = $existing ? $existing->event : $event;

        if ($recordedEvent === 'updated') {
            $merged = array_filter($merged, fn ($change) => $change['before'] !== $change['after']);
        }

        if ($event === 'updated' && ! $merged) {
            $existing?->delete();

            return;
        }

        $actorName = $context->actorId !== null
            ? $context->actorName
            : ($actor ? $actor->name : 'Sistema');

        $contextName = $context->context === 'system' && $actor
            ? ($actor->is_saas_admin ? 'platform' : 'account')
            : $context->context;

        $values = [
            'operation_id' => $operationId,
            'actor_id' => $context->actorId ?? $actor?->id,
            'actor_name' => $actorName,
            'account_id' => $accountId,
            'context' => $contextName,
            'represented_user_id' => $context->representedUserId,
            'entity_type' => $type,
            'entity_id' => $id,
            'entity_name' => $label,
            'event' => $recordedEvent,
            'changes' => $merged,
            'created_at' => $existing ? $existing->created_at : now(),
        ];

        // Eventos sem empresa omitem as colunas, gravadas desde a E04, para não depender delas em migrações antigas.
        if ($companyId !== null) {
            $values['company_id'] = $companyId;
        }

        if ($establishmentId !== null) {
            $values['establishment_id'] = $establishmentId;
        }

        $membershipId = $context->membershipIdFor($actor, $accountId);

        if ($membershipId !== null) {
            $values['membership_id'] = $membershipId;
        }

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
    public static function relationship(
        Model&Auditable $model,
        string $field,
        array $before,
        array $after,
        string $label,
    ): void {
        $before = array_map('intval', $before);
        $after = array_map('intval', $after);
        sort($before);
        sort($after);

        if ($before === $after) {
            return;
        }

        $names = function (array $ids) use ($field, $model): array {
            $query = $field === 'permission_ids'
                ? Permission::query()
                : Tag::where('account_id', $model->auditAccountId());

            $labels = $query->whereIn('id', $ids)->pluck('name', 'id');

            return array_map(fn ($id) => $labels[$id] ?? 'Registro #'.$id, $ids);
        };

        self::record(
            $model::auditType(),
            (int) $model->getKey(),
            $model->auditAccountId(),
            'updated',
            (string) ($model->getAttribute('name') ?? '#'.$model->getKey()),
            [$field => ['label' => $label, 'before' => $names($before), 'after' => $names($after)]],
            $model->auditCompanyId(),
            $model->auditEstablishmentId(),
        );
    }
}
