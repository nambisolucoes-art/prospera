<?php

namespace App\Domains\Usuarios\Application\Actions;

use App\Domains\Usuarios\Application\Data\UpdateProfileData;
use App\Domains\Usuarios\Domain\DTOs\UserIdentityDTO;
use App\Models\User;

final class UpdateProfileAction
{
    public function execute(User $user, UpdateProfileData $data): void
    {
        $user->applyIdentity(new UserIdentityDTO($data->name, $data->email));
        $user->saveOrFail();
    }
}
