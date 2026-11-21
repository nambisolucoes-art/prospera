<?php

namespace App\Domains\Clientes\Domain\Models;

use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use App\Domains\Etiquetas\Domain\Concerns\NamesAuditedTags;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Pessoas\Domain\Models\Person;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Relação comercial da pessoa com uma empresa (E07). A pessoa continua sendo o cadastro central da conta.
 *
 * @property int $account_id
 * @property int $company_id
 * @property int $person_id
 * @property bool $active
 */
#[Fillable(['account_id', 'company_id', 'person_id', 'active'])]
class Customer extends Model implements Auditable
{
    use AuditsChanges;
    use NamesAuditedTags {
        NamesAuditedTags::auditRelationNames insteadof AuditsChanges;
    }

    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public static function register(int $accountId, int $companyId, int $personId): self
    {
        return new self([
            'account_id' => $accountId,
            'company_id' => $companyId,
            'person_id' => $personId,
            'active' => true,
        ]);
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    // Nome do histórico: a pessoa e a empresa da relação, lidos no mesmo tenant.
    public function getNameAttribute(): ?string
    {
        $row = DB::table('people')
            ->join('companies', function ($join): void {
                $join->on('companies.account_id', '=', 'people.account_id');
            })
            ->where('people.account_id', $this->account_id)
            ->where('people.id', $this->person_id)
            ->where('companies.id', $this->company_id)
            ->first(['people.name', 'companies.name as company_name', 'companies.trade_name']);

        if ($row === null) {
            return null;
        }

        return $row->name.' ('.($row->trade_name ?: $row->company_name).')';
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function auditType(): string
    {
        return 'customer';
    }

    /** @return array<string,string> */
    public static function auditedFields(): array
    {
        return [
            'account_id' => 'Conta',
            'company_id' => 'Empresa',
            'person_id' => 'Pessoa',
            'active' => 'Situação',
        ];
    }
}
