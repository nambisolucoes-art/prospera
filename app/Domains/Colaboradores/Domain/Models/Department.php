<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Domain\Models;

use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use App\Domains\Colaboradores\Domain\DTOs\Departments\CreateDepartmentDTO;
use App\Domains\Colaboradores\Domain\DTOs\Departments\UpdateDepartmentDTO;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Support\NormalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Departamento da empresa (S03). Não é um cargo de acesso e não concede permissão.
 *
 * @property int $account_id
 * @property int $company_id
 * @property string $name
 * @property string $normalized_name
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company $company
 */
#[Fillable(['account_id', 'company_id', 'name', 'normalized_name', 'active'])]
class Department extends Model implements Auditable
{
    use AuditsChanges;

    protected $table = 'departments';

    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function auditType(): string
    {
        return 'department';
    }

    /** @return array<string,string> */
    public static function auditedFields(): array
    {
        return [
            'account_id' => 'Conta',
            'company_id' => 'Empresa',
            'name' => 'Nome',
            'active' => 'Situação',
        ];
    }

    /** @return array<string,string> */
    public static function unauditedFields(): array
    {
        return ['normalized_name' => 'Derivado do nome, sem informação própria.'];
    }

    public static function register(CreateDepartmentDTO $data, int $accountId, int $companyId): self
    {
        return new self([
            'account_id' => $accountId,
            'company_id' => $companyId,
            'name' => NormalizedName::display($data->name),
            'normalized_name' => NormalizedName::key($data->name),
            'active' => $data->active,
        ]);
    }

    public function revise(UpdateDepartmentDTO $data): void
    {
        $this->fill([
            'name' => NormalizedName::display($data->name),
            'normalized_name' => NormalizedName::key($data->name),
            'active' => $data->active,
        ]);
    }
}
