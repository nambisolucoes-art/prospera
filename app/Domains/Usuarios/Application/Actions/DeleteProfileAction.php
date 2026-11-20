<?php

namespace App\Domains\Usuarios\Application\Actions;

use App\Domains\Usuarios\Application\Data\DeleteProfileData;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

final class DeleteProfileAction
{
    public function execute(DeleteProfileData $data, Session $session): void
    {
        Auth::logout();

        $photo = $data->user->getConnection()->transaction(function () use ($data) {
            $user = User::query()->lockForUpdate()->findOrFail($data->user->id);
            $photo = $user->profile_photo;
            $user->delete();

            return $photo;
        });

        $session->invalidate();
        $session->regenerateToken();

        if ($photo !== null) {
            Storage::disk('local')->delete(User::profilePhotoPath($data->user->id, $photo));
        }
    }
}
