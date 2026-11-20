<?php

namespace App\Domains\Usuarios\Application\Actions;

use App\Domains\Usuarios\Application\Data\UpdateProfileData;
use App\Models\User;

final class UpdateProfileAction
{
    public function execute(User $user, UpdateProfileData $data): void
    {
        $user->name = $data->name;
        $user->saveOrFail();
    }
}
