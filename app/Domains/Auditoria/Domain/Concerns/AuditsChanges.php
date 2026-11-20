<?php

namespace App\Domains\Auditoria\Domain\Concerns;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Auditoria\Infrastructure\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Model;

/** @phpstan-require-extends Model */
trait AuditsChanges
{
    public static function bootAuditsChanges(): void
    {
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::registerModelEvent($event, fn ($model) => app(AuditObserver::class)->{$event}($model));
        }
    }

    /** @return array<string,string> */
    public static function unauditedFields(): array
    {
        return [];
    }

    public function auditAccountId(): ?int
    {
        $id = $this->getAttribute('account_id') ?? $this->getAttribute('client_id');

        return $id === null ? null : (int) $id;
    }

    public function auditCompanyId(): ?int
    {
        $id = $this->getAttribute('company_id');

        return $id === null ? null : (int) $id;
    }

    public function auditEstablishmentId(): ?int
    {
        $id = $this->getAttribute('establishment_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Registra a mudança de um relacionamento, que não dispara os eventos do Eloquent.
     *
     * @param  array<int,mixed>  $before
     * @param  array<int,mixed>  $after
     */
    public function recordAuditFieldChange(string $field, array $before, array $after, string $label): void
    {
        AuditLog::relationship($this, $field, $before, $after, $label);
    }

    /** @param array<string,mixed> $options */
    public function save(array $options = []): bool
    {
        return (bool) AuditLog::transaction(fn () => parent::save($options));
    }

    public function delete(): ?bool
    {
        return AuditLog::transaction(fn () => parent::delete());
    }
}
