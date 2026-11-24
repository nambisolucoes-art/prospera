<?php

namespace Database\Seeders\Acessos;

use App\Domains\Acessos\Application\Actions\SyncPermissionsAction;
use Illuminate\Database\Seeder;

/** Catálogo de permissões descoberto no código, o mesmo do comando permissions:sync. */
final class PermissionCatalogSeeder extends Seeder
{
    public function run(SyncPermissionsAction $sync): void
    {
        $sync->execute();
    }
}
