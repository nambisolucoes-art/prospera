<?php

declare(strict_types=1);

namespace App\Domains\Usuarios\UI\Fortify;

use App\Domains\Usuarios\Application\Actions\UpdatePasswordAction;
use App\Domains\Usuarios\Application\Data\PasswordData;
use App\Models\User;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

final class ResetUserPassword implements ResetsUserPasswords
{
    public function __construct(private readonly UpdatePasswordAction $update) {}

    /** @param array<string, mixed> $input */
    public function reset(User $user, #[\SensitiveParameter] array $input): void
    {
        $this->update->execute(PasswordData::fromInput($input), $user);
    }
}
