<?php

declare(strict_types=1);

namespace App\Domains\Usuarios\Application\Actions;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Usuarios\Application\Data\PasswordData;
use App\Models\User;

final readonly class UpdatePasswordAction
{
    public function __construct(private EndOtherIdentitySessionsAction $sessions) {}

    public function execute(User $user, #[\SensitiveParameter] PasswordData $data): void
    {
        AuditLog::transaction(function () use ($user, $data): void {
            $user->forceFill(['password' => $data->password, 'password_setup_pending' => false]);
            $this->sessions->execute($user, request()->hasSession() ? request()->session()->getId() : null);
        });
    }
}
