<?php

use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * E04 (DA-14, PD-05): remove o exemplo didático de pontos.
 *
 * Reversível enquanto a tabela estiver vazia. Com registros, a migração é interrompida para que os dados
 * sejam apresentados e a remoção aprovada antes de qualquer exclusão.
 */
return new class extends Migration
{
    private const SLUGS = ['account.points.list', 'account.points.create', 'account.points.update'];

    private const SUBSTITUTE = 'account.establishments.list';

    public function up(): void
    {
        $points = DB::table('points')->count();

        if ($points > 0) {
            throw new RuntimeException(
                "A tabela points contém {$points} registros. Exporte os dados e aprove a remoção antes de migrar.",
            );
        }

        AuditLog::transaction(function (): void {
            // O cargo inicial Usuário troca a listagem de pontos pela de estabelecimentos (seção 8.4).
            $members = Role::where('system_key', 'member')
                ->whereHas('permissions', fn ($query) => $query->where('slug', 'account.points.list'))
                ->get();

            if ($members->isNotEmpty()) {
                $substitute = DB::table('permissions')->where('slug', self::SUBSTITUTE)->value('id')
                    ?? DB::table('permissions')->insertGetId([
                        'slug' => self::SUBSTITUTE,
                        'name' => 'Listar: Estabelecimentos',
                        'scope' => 'account',
                        'module' => 'establishments',
                        'account_id' => null,
                        'is_system' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                foreach ($members as $member) {
                    $before = $member->permissionIds();
                    $member->permissions()->syncWithoutDetaching([$substitute]);
                    $member->recordAuditFieldChange('permission_ids', $before, $member->permissionIds(), 'Permissões');
                }
            }

            $permissions = DB::table('permissions')
                ->whereIn('slug', self::SLUGS)
                ->get(['id', 'slug', 'name']);

            $changes = [];

            foreach ($permissions as $permission) {
                $roles = DB::table('permission_role')
                    ->join('roles', 'roles.id', '=', 'permission_role.role_id')
                    ->where('permission_role.permission_id', $permission->id)
                    ->pluck('roles.name')
                    ->all();

                $changes[$permission->slug] = [
                    'label' => $permission->name,
                    'before' => $roles === [] ? $permission->name : $permission->name.' ('.implode(', ', $roles).')',
                    'after' => null,
                ];
            }

            // O pivô dos cargos é removido em cascata junto com a definição.
            DB::table('permissions')
                ->whereIn('slug', self::SLUGS)
                ->delete();

            if ($changes !== []) {
                AuditLog::record(
                    'permission-catalog',
                    0,
                    null,
                    'catalog-synchronized',
                    'Catálogo de permissões',
                    $changes,
                );
            }
        });

        Schema::drop('points');
    }

    public function down(): void
    {
        Schema::create('points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->nullable()->constrained()->restrictOnDelete();
            $table->index('establishment_id');
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->index('client_id');
        });

        // Restaura as definições e o acesso que os cargos iniciais tinham antes da remoção.
        foreach (self::SLUGS as $slug) {
            $action = explode('.', $slug)[2];
            $name = ['list' => 'Listar', 'create' => 'Cadastrar', 'update' => 'Editar'][$action].': Pontos';

            DB::table('permissions')->insertOrIgnore([
                'slug' => $slug,
                'name' => $name,
                'scope' => 'account',
                'module' => 'points',
                'account_id' => null,
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', self::SLUGS)
            ->pluck('id', 'slug');

        $substitute = DB::table('permissions')->where('slug', self::SUBSTITUTE)->value('id');

        foreach (DB::table('roles')->whereIn('system_key', ['administrator', 'member'])->get() as $role) {
            $slugs = $role->system_key === 'administrator' ? self::SLUGS : ['account.points.list'];

            foreach ($slugs as $slug) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $role->id,
                    'permission_id' => $permissionIds[$slug],
                ]);
            }

            if ($role->system_key === 'member' && $substitute !== null) {
                DB::table('permission_role')
                    ->where('role_id', $role->id)
                    ->where('permission_id', $substitute)
                    ->delete();
            }
        }
    }
};
