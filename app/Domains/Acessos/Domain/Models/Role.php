<?php

namespace App\Domains\Acessos\Domain\Models;

use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        return ['name' => 'Nome', 'account_id' => 'Conta', 'system_key' => 'Cargo inicial'];
    }

    /** @return array<int,int> */
    public function permissionIds(): array
    {
        return $this->permissions()
            ->orderBy('permissions.id')
            ->pluck('permissions.id')
            ->all();
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->where('permissions.is_system', true);
    }
}
