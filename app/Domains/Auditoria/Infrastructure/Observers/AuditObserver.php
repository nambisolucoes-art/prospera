<?php

namespace App\Domains\Auditoria\Infrastructure\Observers;

use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

final class AuditObserver
{
    public function created(Model&Auditable $entity): void
    {
        $this->record($entity, 'created');
    }

    public function updated(Model&Auditable $entity): void
    {
        $this->record($entity, 'updated');
    }

    public function deleted(Model&Auditable $entity): void
    {
        $this->record($entity, 'deleted');
    }

    private function value(Model $entity, string $field, bool $before): mixed
    {
        [$root] = explode('.', $field);
        $value = $before ? $entity->getOriginal($root) : $entity->getAttribute($root);
        if (str_contains($field, '.')) {
            $value = Arr::get(is_array($value) ? $value : [], substr($field, strlen($root) + 1));
        }

        return $value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : $value;
    }

    private function safe(Model $entity, string $field, mixed $value, bool $before, string $event): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (preg_match('/password|secret|token|recovery|credential|api_key/i', $field)) {
            return $before ? 'Configurado' : ($event === 'created' ? 'Configurado' : 'Atualizado');
        }
        if ($field === 'profile_photo') {
            return $before ? 'Foto anterior' : 'Foto atualizada';
        }
        if ($field === 'document') {
            return $before ? 'Documento anterior' : 'Documento informado';
        }
        if ($field === 'role_id') {
            return Role::where('account_id', $entity->getAttribute('account_id'))->whereKey((int) $value)->value('name') ?? 'Cargo #'.$value;
        }
        if (str_ends_with($field, '_confirmed_at') || $field === 'email_verified_at') {
            return 'Confirmado';
        }

        return $value;
    }

    private function record(Model&Auditable $entity, string $event): void
    {
        $changes = [];
        foreach ($entity::auditedFields() as $field => $label) {
            $before = $event === 'created' ? null : $this->value($entity, $field, true);
            $after = $event === 'deleted' ? null : $this->value($entity, $field, false);
            if ($before === $after || ($before === null && $after === []) || ($before === [] && $after === null)) {
                continue;
            }
            $changes[$field] = ['label' => $label, 'before' => $this->safe($entity, $field, $before, true, $event), 'after' => $this->safe($entity, $field, $after, false, $event)];
        }
        if (! $changes && $event === 'updated') {
            return;
        }
        AuditLog::record($entity::auditType(), (int) $entity->getKey(), $entity->auditAccountId(), $event, (string) ($entity->getAttribute('name') ?? '#'.$entity->getKey()), $changes);
    }
}
