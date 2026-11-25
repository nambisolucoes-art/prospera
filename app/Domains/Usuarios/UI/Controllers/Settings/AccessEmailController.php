<?php

declare(strict_types=1);

namespace App\Domains\Usuarios\UI\Controllers\Settings;

use App\Domains\Usuarios\Application\Actions\ConfirmAccessEmailChangeAction;
use App\Domains\Usuarios\Application\Actions\RequestAccessEmailChangeAction;
use App\Domains\Usuarios\Application\Data\ConfirmAccessEmailChangeData;
use App\Domains\Usuarios\Application\Data\RequestAccessEmailChangeData;
use App\Support\ApiPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AccessEmailController
{
    public function request(Request $request, RequestAccessEmailChangeAction $action): RedirectResponse
    {
        $data = RequestAccessEmailChangeData::fromInput($request->all());

        $action->execute($data, $request->user());
        ApiPage::toast('Enviamos um link de confirmação para o novo e-mail.');

        return to_route('profile.edit');
    }

    public function confirm(Request $request, ConfirmAccessEmailChangeAction $action): RedirectResponse
    {
        abort_unless($request->integer('identity') === $request->user()->id, 403);

        $data = ConfirmAccessEmailChangeData::fromInput($request->query());

        $updated = $action->execute($data, $request->user(), $request->session()->getId());

        if ($updated) {
            $request->user()->refresh();
            ApiPage::toast('E-mail de acesso atualizado.');
        } else {
            $request->session()->flash('status', 'email-change-unavailable');
        }

        return to_route('profile.edit');
    }
}
