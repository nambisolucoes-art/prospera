<?php

use App\Domains\Auditoria\Application\Actions\AuditLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        AuditLog::transaction(function (): void {
            $permissions = DB::table('permissions')
                ->where('is_system', true)
                ->whereIn('slug', ['account.people.list', 'account.users.update'])
                ->get(['id', 'slug', 'name']);

            if ($permissions->isEmpty()) {
                return;
            }

            $ids = $permissions->pluck('id');
            DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();

            $changes = $permissions->mapWithKeys(fn ($permission) => [
                $permission->slug => ['label' => $permission->name, 'before' => $permission->name, 'after' => null],
            ])->all();

            AuditLog::record(
                'permission-catalog',
                0,
                null,
                'catalog-synchronized',
                'Catálogo de permissões',
                $changes,
            );
        });
    }

    public function down(): void
    {
        // A versão anterior pode recriar a definição com permissions:sync. As concessões removidas não voltam.
    }
};
