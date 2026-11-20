<?php

namespace App\Domains\Usuarios\UI\Controllers\Settings;

use App\Domains\Usuarios\Application\Actions\ChangeAccessEmailAction;
use App\Domains\Usuarios\Application\Data\ChangeAccessEmailData;
use App\Support\ApiPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AccessEmailController
{
    public function request(Request $request, ChangeAccessEmailAction $change): RedirectResponse
    {
        $change->request($request->user(), ChangeAccessEmailData::fromInput($request->all(), confirmPassword: true));

        ApiPage::toast('Enviamos um link de confirmação para o novo e-mail.');

        return to_route('profile.edit');
    }

    public function confirm(Request $request, ChangeAccessEmailAction $change): RedirectResponse
    {
        abort_unless($request->integer('identity') === $request->user()->id, 403);

        $updated = $change->confirm(
            $request->user(),
            ChangeAccessEmailData::fromInput($request->query()),
            $request->string('revision')->toString(),
            $request->session()->getId(),
        );

        if ($updated) {
            $request->user()->refresh();
            ApiPage::toast('E-mail de acesso atualizado.');
        } else {
            $request->session()->flash('status', 'email-change-unavailable');
        }

        return to_route('profile.edit');
    }
}
