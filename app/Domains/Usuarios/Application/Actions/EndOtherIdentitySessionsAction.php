<?php

declare(strict_types=1);

namespace App\Domains\Usuarios\Application\Actions;

use App\Http\Middleware\EnsureIdentitySessionCurrent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class EndOtherIdentitySessionsAction
{
    public function execute(User $user, ?string $currentSession): void
    {
        $user->setRememberToken(null);
        $user->saveOrFail();

        if ($currentSession !== null && request()->user()?->id === $user->id) {
            request()->session()->put(EnsureIdentitySessionCurrent::KEY, [
                'id' => $user->id,
                'value' => $user->emailChangeRevision(),
            ]);
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->when($currentSession !== null, fn ($query) => $query->where('id', '!=', $currentSession))
            ->delete();
    }
}
