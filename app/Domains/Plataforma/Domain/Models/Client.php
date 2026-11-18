<?php

namespace App\Domains\Plataforma\Domain\Models;

use App\Domains\Plataforma\Domain\DTOs\AccountDetailsDTO;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property array<string, mixed>|null $details */
#[Fillable(['name', 'email', 'person_type', 'document', 'active', 'details', 'plan'])]
class Client extends Model
{
    public function applyDetails(AccountDetailsDTO $details): void
    {
        $attributes = ['name' => $details->name, ...$details->attributes];
        if (isset($attributes['details'])) {
            $incoming = $attributes['details'];
            $attributes['details'] = array_replace_recursive($this->details ?? [], $incoming);
            foreach (['additional_phones', 'additional_emails'] as $field) {
                if (isset($incoming[$field])) {
                    $attributes['details'][$field] = $incoming[$field];
                }
            }
        }

        $this->fill($attributes);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['active' => 'boolean', 'details' => 'array'];
    }

    /** @return HasMany<Point, $this> */
    public function points(): HasMany
    {
        return $this->hasMany(Point::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'account_id');
    }
}
