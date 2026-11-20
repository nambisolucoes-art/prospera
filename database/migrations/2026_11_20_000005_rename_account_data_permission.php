<?php

use App\Domains\Auditoria\Application\Actions\AuditLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * E04: account.establishment.update passa a ser account.account-data.update (Dados da conta).
 *
 * A definição é renomeada no lugar, o que preserva o pivô dos cargos. Reversível pelo mapeamento inverso.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rename(
            'account.establishment.update',
            'account.account-data.update',
            'account-data',
            'Editar: Dados da conta',
        );
    }

    public function down(): void
    {
        $this->rename(
            'account.account-data.update',
            'account.establishment.update',
            'establishment',
            'Editar: Estabelecimento',
        );
    }

    private function rename(string $from, string $to, string $module, string $name): void
    {
        AuditLog::transaction(function () use ($from, $to, $module, $name): void {
            $old = DB::table('permissions')->where('slug', $from)->first(['id', 'name']);

            if ($old === null) {
                return;
            }

            $existing = DB::table('permissions')->where('slug', $to)->value('id');

            if ($existing !== null) {
                // A sincronização já criou o nome novo: os cargos recebem o vínculo e a definição antiga sai.
                $roleIds = DB::table('permission_role')
                    ->where('permission_id', $old->id)
                    ->pluck('role_id');

                foreach ($roleIds as $roleId) {
                    DB::table('permission_role')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $existing,
                    ]);
                }

                DB::table('permissions')
                    ->where('id', $old->id)
                    ->delete();
            } else {
                DB::table('permissions')
                    ->where('id', $old->id)
                    ->update([
                        'slug' => $to,
                        'module' => $module,
                        'name' => $name,
                        'updated_at' => now(),
                    ]);
            }

            AuditLog::record(
                'permission-catalog',
                0,
                null,
                'catalog-synchronized',
                'Catálogo de permissões',
                [$to => ['label' => $name, 'before' => $old->name.' ('.$from.')', 'after' => $name]],
            );
        });
    }
};
