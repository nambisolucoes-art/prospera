<?php

declare(strict_types=1);

namespace App\Domains\Usuarios\UI\Controllers\Settings;

use App\Domains\Usuarios\Application\Actions\DeleteProfileAction;
use App\Domains\Usuarios\Application\Actions\UpdateProfileAction;
use App\Domains\Usuarios\Application\Data\DeleteProfileData;
use App\Domains\Usuarios\Application\Data\UpdateProfileData;
use App\Http\Controllers\Controller;
use App\Support\ApiPage;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): JsonResponse|View
    {
        return ApiPage::respond([
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(Request $request, UpdateProfileAction $update): RedirectResponse
    {
        $user = $request->user();
        $update->execute(UpdateProfileData::fromInput($request->all()), $user);

        ApiPage::toast('Perfil atualizado.');

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(Request $request, DeleteProfileAction $delete): RedirectResponse
    {
        $data = DeleteProfileData::fromInput($request->all(), $request->user());
        $delete->execute($data, $request->session());

        return redirect('/');
    }
}
