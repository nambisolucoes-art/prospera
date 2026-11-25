<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Domain\Models;

use App\Domains\Acessos\Domain\Concerns\NamesAuditedRole;
use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $account_id
 * @property int $membership_id
 * @property int $role_id
 * @property string $scope
 * @property int|null $company_id
 * @property int|null $establishment_id
 * @property int|null $granted_by
 */
final class RoleAssignment extends Model implements Auditable
{
    use AuditsChanges;
    use NamesAuditedRole {
        NamesAuditedRole::auditRelationNames insteadof AuditsChanges;
    }

    public static function auditType(): string
    {
        return 'role-assignment';
    }

    /** @return array<string,string> */
    public static function auditedFields(): array
    {
        return [
            'account_id' => 'Conta',
            'membership_id' => 'Vínculo',
            'role_id' => 'Cargo',
            'scope' => 'Escopo',
            'company_id' => 'Empresa',
            'establishment_id' => 'Estabelecimento',
            'granted_by' => 'Concedido por',
        ];
    }

    /** @return BelongsTo<AccountMembership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(AccountMembership::class, 'membership_id');
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
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

    public function node(): AccessNode
    {
        return match ($this->scope) {
            'company' => AccessNode::company($this->account_id, (int) $this->company_id),
            'establishment' => AccessNode::establishment(
                $this->account_id,
                (int) $this->company_id,
                (int) $this->establishment_id,
            ),
            default => AccessNode::tenant($this->account_id),
        };
    }

    public static function grant(
        AccountMembership $membership,
        Role $role,
        AccessNode $node,
        ?int $grantedBy,
    ): self {
        $assignment = new self;
        $assignment->account_id = $membership->account_id;
        $assignment->membership_id = $membership->id;
        $assignment->role_id = $role->id;
        $assignment->scope = $node->type;
        $assignment->company_id = $node->companyId;
        $assignment->establishment_id = $node->establishmentId;
        $assignment->granted_by = $grantedBy;

        return $assignment;
    }
}
