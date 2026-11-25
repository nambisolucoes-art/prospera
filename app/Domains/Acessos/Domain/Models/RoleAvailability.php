<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Domain\Models;

use App\Domains\Acessos\Domain\Concerns\NamesAuditedRole;
use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $account_id
 * @property int $role_id
 * @property int $company_id
 * @property int|null $establishment_id
 */
final class RoleAvailability extends Model implements Auditable
{
    use AuditsChanges;
    use NamesAuditedRole {
        NamesAuditedRole::auditRelationNames insteadof AuditsChanges;
    }

    protected $table = 'role_availability';

    public $timestamps = false;

    public static function auditType(): string
    {
        return 'role-availability';
    }

    /** @return array<string,string> */
    public static function auditedFields(): array
    {
        return [
            'account_id' => 'Conta',
            'role_id' => 'Cargo',
            'company_id' => 'Empresa',
            'establishment_id' => 'Estabelecimento',
        ];
    }
}
