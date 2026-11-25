<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['slug', 'name', 'scope', 'module', 'account_id', 'is_system'])]
class Permission extends Model
{
    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }
}
