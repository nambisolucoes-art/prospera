<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Domain\Models;

use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use App\Domains\Colaboradores\Domain\DTOs\Employees\CreateEmployeeDTO;
use App\Domains\Colaboradores\Domain\DTOs\Employees\UpdateEmployeeDTO;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Pessoas\Domain\Models\Person;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Relação de colaborador da pessoa com uma empresa (S04). A identidade continua em Pessoas: aqui ficam só a
 * situação e os contatos profissionais próprios da empresa. Não é usuário e não concede permissão.
 *
 * @property int $id
 * @property int $account_id
 * @property int $company_id
 * @property int $person_id
 * @property bool $active
 * @property string|null $work_email
 * @property string|null $work_phone
 * @property string|null $work_whatsapp
 */
#[Fillable(['account_id', 'company_id', 'person_id', 'active', 'work_email', 'work_phone', 'work_whatsapp'])]
class Employee extends Model implements Auditable
{
    use AuditsChanges;

    protected $table = 'employees';

    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public static function register(CreateEmployeeDTO $data, int $accountId, int $companyId, int $personId): self
    {
        return new self([
            'account_id' => $accountId,
            'company_id' => $companyId,
            'person_id' => $personId,
            'active' => true,
            'work_email' => self::clean($data->workEmail),
            'work_phone' => self::clean($data->workPhone),
            'work_whatsapp' => self::clean($data->workWhatsapp),
        ]);
    }

    public function revise(UpdateEmployeeDTO $data): void
    {
        $this->fill([
            'active' => $data->active,
            'work_email' => self::clean($data->workEmail),
            'work_phone' => self::clean($data->workPhone),
            'work_whatsapp' => self::clean($data->workWhatsapp),
        ]);
    }

    private static function clean(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    // Nome do histórico: a pessoa e a empresa do vínculo, lidos no mesmo tenant.
    public function getNameAttribute(): ?string
    {
        $row = DB::table('people')
            ->join('companies', 'companies.account_id', '=', 'people.account_id')
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
        return 'employee';
    }

    /** @return array<string,string> */
    public static function auditedFields(): array
    {
        return [
            'account_id' => 'Conta',
            'company_id' => 'Empresa',
            'person_id' => 'Pessoa',
            'active' => 'Situação',
            'work_email' => 'E-mail profissional',
            'work_phone' => 'Telefone profissional',
            'work_whatsapp' => 'WhatsApp profissional',
        ];
    }
}
