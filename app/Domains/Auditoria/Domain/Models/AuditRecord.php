<?php

declare(strict_types=1);

namespace App\Domains\Auditoria\Domain\Models;

use App\Domains\Auditoria\Application\Actions\AuditContext;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<string,array{label:string,before:mixed,after:mixed}> $changes
 * @property Carbon $created_at
 */
class AuditRecord extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected static function booted(): void
    {
        // O histórico só muda dentro da operação que o gravou, ao agrupar mudanças do mesmo registro.
        $immutable = function (AuditRecord $record): void {
            if ($record->getOriginal('operation_id') !== app(AuditContext::class)->operationId) {
                throw new \LogicException('O histórico de auditoria não pode ser alterado nem excluído.');
            }
        };

        static::updating($immutable);
        static::deleting($immutable);
    }

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
            'actor_id' => 'integer',
            'account_id' => 'integer',
            'company_id' => 'integer',
            'establishment_id' => 'integer',
            'represented_user_id' => 'integer',
            'membership_id' => 'integer',
            'entity_id' => 'integer',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'account_id');
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Establishment, $this> */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    // Nível organizacional do evento, derivado das colunas gravadas (seção 11.1 do plano).
    public function level(): string
    {
        if ($this->account_id === null) {
            return 'platform';
        }

        if ($this->establishment_id !== null) {
            return 'establishment';
        }

        return $this->company_id === null ? 'tenant' : 'company';
    }
}
