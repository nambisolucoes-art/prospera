<?php

namespace App\Domains\Usuarios\Application\Queries;

use App\Models\User;

final class UserEmailExistsQuery
{
    public function execute(string $email, ?int $ignoreId = null): bool
    {
        return User::whereRaw('lower(email) = ?', [$email])
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
