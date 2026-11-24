<?php

namespace Database\Seeders\Plataforma;

use App\Domains\Usuarios\Application\Actions\CreateUserAction;
use App\Domains\Usuarios\Application\Data\CreateUserData;
use App\Domains\Usuarios\Domain\Enums\UserRole;
use Database\Seeders\Support\SeedCatalog;
use Illuminate\Database\Seeder;

/** Administrador da plataforma SaaS: não pertence a nenhuma conta. */
final class PlatformAdministratorSeeder extends Seeder
{
    public function run(CreateUserAction $create): void
    {
        $data = CreateUserData::fromInput([
            'name' => 'Administrador da Plataforma',
            'email' => SeedCatalog::PLATFORM_ADMINISTRATOR,
            'mode' => 'manual',
            'password' => SeedCatalog::PASSWORD,
            'password_confirmation' => SeedCatalog::PASSWORD,
        ]);

        $create->execute($data, UserRole::PlatformAdministrator);
    }
}
