<?php

namespace App\Domains\Usuarios\Application\Actions;

use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Usuarios\Application\Data\DeleteProfileData;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class DeleteProfileAction
{
    public function execute(DeleteProfileData $data, Session $session): void
    {
        $photo = AuditLog::transaction(function () use ($data) {
            $memberships = AccountMembership::where('user_id', $data->user->id)->orderBy('account_id')->get();

            if ($memberships->isNotEmpty()) {
                Client::whereKey($memberships->pluck('account_id')->all())->orderBy('id')->lockForUpdate()->get();
            }
            $user = User::query()->lockForUpdate()->findOrFail($data->user->id);
            foreach ($memberships as $membership) {
                $locked = AccountMembership::whereKey($membership->id)->lockForUpdate()->firstOrFail();

                if ($locked->isLastActiveAdministrator()) {
                    throw ValidationException::withMessages(['password' => 'Mantenha ao menos um administrador na conta.']);
                }

                foreach ($locked->assignments()->get() as $assignment) {
                    $assignment->delete();
                }

                $locked->delete();
            }

            $photo = $user->profile_photo;
            $user->delete();

            return $photo;
        });

        Auth::logout();
        $session->invalidate();
        $session->regenerateToken();

        if ($photo !== null) {
            Storage::disk('local')->delete(User::profilePhotoPath($data->user->id, $photo));
        }
    }
}
