<?php

namespace App\Domains\Usuarios\Application\Actions;

use App\Domains\Usuarios\Application\Data\PasswordData;
use App\Models\User;

final class UpdatePasswordAction
{
    public function execute(User $user, #[\SensitiveParameter] PasswordData $data): void
    {
        $user->forceFill(['password' => $data->password])->saveOrFail();
    }
}
