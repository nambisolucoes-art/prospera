<?php

declare(strict_types=1);

namespace App\Domains\Auditoria\Application\Actions;

use App\Domains\Auditoria\Domain\Contracts\Auditable;
use App\Domains\Auditoria\Domain\Exceptions\AuditWriteFailed;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
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
        // Migrations antigas da instalação são anteriores à tabela central de histórico. Fora do console, a
        // ausência da tabela falha na gravação e desfaz a operação, sem uma consulta de esquema por evento.
        if (app()->runningInConsole() && ! Schema::hasTable('audit_records')) {
            return;
        }

        // O nó do evento segue a árvore: unidade exige empresa, e empresa exige conta (FKs e CHECKs da E08).
        if ($establishmentId !== null && $companyId === null) {
            throw new \LogicException('Evento de estabelecimento sem a empresa responsável.');
        }

        if ($companyId !== null && $accountId === null) {
            throw new \LogicException('Evento de empresa sem a conta responsável.');
        }

        $context = app(AuditContext::class);
        $actor = auth()->user();
        $operationId = $context->operationId ?? (string) Str::uuid();

        // Criação e edição do mesmo registro na mesma operação viram um único evento.
        $events = in_array($event, ['created', 'updated'], true) ? ['created', 'updated'] : [$event];

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
        ];

        // Eventos sem empresa omitem as colunas, gravadas desde a E04, para não depender delas em migrações antigas.
        if ($companyId !== null) {
            $values['company_id'] = $companyId;
        }

        if ($establishmentId !== null) {
            $values['establishment_id'] = $establishmentId;
        }

        // Colunas da E08, omitidas quando vazias para as migrações anteriores a elas.
        if ($context->representedUserName !== null) {
            $values['represented_user_name'] = $context->representedUserName;
        }

        if ($context->inspectionId !== null) {
            $values['inspection_id'] = $context->inspectionId;
        }

        $membershipId = $context->membershipIdFor($actor, $accountId);

        if ($membershipId !== null) {
            $values['membership_id'] = $membershipId;
        }

        try {
            $existing = AuditRecord::where('operation_id', $operationId)
                ->where('entity_type', $type)
                ->where('entity_id', $id)
                ->where('account_id', $accountId)
                ->whereIn('event', $events)
                ->get()
                ->first(fn (AuditRecord $record) => $record->getRawOriginal('company_id') === $companyId
                    && $record->getRawOriginal('establishment_id') === $establishmentId);

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

            $values['event'] = $recordedEvent;
            $values['changes'] = $merged;
            $values['created_at'] = $existing ? $existing->created_at : now();

            if ($existing) {
                $existing->fill($values)->saveOrFail();
            } else {
                AuditRecord::create($values);
            }
        } catch (QueryException $exception) {
            // A operação é desfeita pela exceção; o log recebe só o tipo, a operação e o código do banco.
            throw new AuditWriteFailed(sprintf(
                'Falha ao gravar o histórico de auditoria (%s, operação %s, SQLSTATE %s).',
                $type,
                $operationId,
                $exception->errorInfo[0] ?? 'desconhecido',
            ));
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
        $before = array_values(array_map('intval', $before));
        $after = array_values(array_map('intval', $after));
        sort($before);
        sort($after);

        if ($before === $after) {
            return;
        }

        // O próprio model nomeia os itens; sem nome conhecido, o histórico mostra o identificador.
        $names = fn (array $ids): array => $model->auditRelationNames($field, array_values($ids))
            ?? array_map(fn (int $id): string => 'Registro #'.$id, $ids);

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
