<?php

namespace App\Domains\Acessos\Domain\Models;

use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property string $availability */
#[Fillable(['name', 'account_id', 'system_key'])]
class Role extends Model implements Auditable
{
    use AuditsChanges;

    public static function auditType(): string
    {
        return 'role';
    }

    /** @return array<string,string> */
    public static function auditedFields(): array
    {
        return ['name' => 'Nome', 'account_id' => 'Conta', 'system_key' => 'Cargo inicial', 'availability' => 'Abrangência'];
    }

    /** @return array<int,int> */
    public function permissionIds(): array
    {
        return $this->permissions()
            ->orderBy('permissions.id')
            ->pluck('permissions.id')
            ->all();
    }

    /**
     * Permissões do catálogo (globais) nomeadas no histórico de `permission_ids`.
     *
     * @param  list<int>  $ids
     * @return list<string>|null
     */
    public function auditRelationNames(string $field, array $ids): ?array
    {
        if ($field !== 'permission_ids') {
            return null;
        }

        $names = Permission::whereIn('id', $ids)->pluck('name', 'id');

        return array_map(fn (int $id): string => $names[$id] ?? 'Permissão #'.$id, $ids);
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->where('permissions.is_system', true);
    }

    /** @return HasMany<RoleAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    /** @return HasMany<RoleAvailability, $this> */
    public function availabilityNodes(): HasMany
    {
        return $this->hasMany(RoleAvailability::class);
    }

    /** @param  list<array{company_id:int, establishment_id:?int}>  $nodes */
    public function syncAvailabilityNodes(array $nodes): void
    {
        $existing = $this->availabilityNodes()->get();
        $key = fn (int $company, ?int $unit): string => $company.':'.$unit;
        $desired = [];

        foreach ($nodes as $node) {
            $desired[$key($node['company_id'], $node['establishment_id'])] = $node;
        }

        foreach ($existing as $node) {
            $id = $key($node->company_id, $node->establishment_id);

            if (! isset($desired[$id])) {
                $node->delete();
            }

            unset($desired[$id]);
        }

        foreach ($desired as $node) {
            $availability = new RoleAvailability;
            $availability->account_id = $this->account_id;
            $availability->company_id = $node['company_id'];
            $availability->establishment_id = $node['establishment_id'];
            $this->availabilityNodes()->save($availability);
        }
    }

    public function isAvailableAt(AccessNode $node): bool
    {
        if ($node->accountId !== $this->account_id || ($this->system_key !== null && $node->type !== 'tenant')) {
            return false;
        }

        if ($this->availability === 'tenant') {
            return true;
        }

        if ($node->companyId === null) {
            return false;
        }

        $nodes = $this->relationLoaded('availabilityNodes') ? $this->availabilityNodes : $this->availabilityNodes()->get();

        return $nodes->contains(fn (RoleAvailability $availability): bool => $availability->company_id === $node->companyId
            && ($availability->establishment_id === null || $availability->establishment_id === $node->establishmentId));
    }
}
