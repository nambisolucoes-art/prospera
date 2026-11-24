<?php

namespace App\Domains\Plataforma\UI\Controllers;

use App\Domains\Plataforma\Application\Actions\EndAccountInspectionAction;
use App\Domains\Plataforma\Application\Actions\StartAccountInspectionAction;
use App\Domains\Plataforma\Application\Data\StartAccountInspectionData;
use App\Domains\Plataforma\Application\Queries\AccountInspectionUsersQuery;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AccountInspectionController
{
    public function options(Client $client, AccountInspectionUsersQuery $users): JsonResponse
    {
        abort_unless($client->active, 404);

        $options = $users->list($client)->map(function (User $user) use ($client): array {
            $assignments = $user->memberships->firstOrFail()->assignments;

            return [
                ...$user->only(['id', 'name', 'email']),
                'role_name' => $assignments->pluck('role.name')->unique()->implode(', '),
                'photo_url' => $user->profile_photo ? route('saas.accounts.inspection.photo', [
                    'client' => $client, 'userId' => $user->id, 'version' => $user->profile_photo,
                ], absolute: false) : null,
            ];
        });

        return response()->json($options, headers: ['Cache-Control' => 'private, no-store']);
    }

    public function start(Request $request, Client $client, StartAccountInspectionAction $start): RedirectResponse
    {
        $start->execute(
            $request->user(),
            $client,
            StartAccountInspectionData::fromInput($request->all()),
            $request->session(),
        );

        return redirect('/contas/'.$client->code);
    }

    public function end(Request $request, EndAccountInspectionAction $end): RedirectResponse
    {
        $restored = $end->execute($request->user(), $request->session());

        return to_route($restored ? 'dashboard' : 'login');
    }
}
