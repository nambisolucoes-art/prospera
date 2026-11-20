<?php

use App\Domains\Acessos\Application\Actions\SyncPermissionsAction;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_records', function (Blueprint $t) {
            $t->id();
            $t->uuid('operation_id')->index();
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('actor_name');
            $t->unsignedBigInteger('account_id')->nullable();
            $t->string('context', 40);
            $t->unsignedBigInteger('represented_user_id')->nullable();
            $t->string('entity_type', 80);
            $t->unsignedBigInteger('entity_id');
            $t->string('entity_name');
            $t->string('event', 100);
            $t->json('changes');
            $t->timestamp('created_at');
            $t->string('legacy_key')->nullable()->unique();
            $t->index(['account_id', 'created_at', 'id']);
            $t->index(['actor_id', 'created_at']);
            $t->index(['entity_type', 'event']);
        });
        foreach (['directory_audit_events', 'access_catalog_events'] as $table) {
            $legacyFields = [
                'name',
                'email',
                'phone',
                'module',
                'color',
                'system_key',
                'permission_ids',
                'tag_ids',
                'role_id',
                'person_id',
                'user_id',
            ];

            DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, $legacyFields) {
                foreach ($rows as $row) {
                    $old = json_decode($row->changes, true) ?? [];
                    $changes = [];
                    $before = $old['before'] ?? [];
                    $after = $old['after'] ?? [];
                    $keys = array_unique([
                        ...array_keys(is_array($before) ? $before : []),
                        ...array_keys(is_array($after) ? $after : []),
                    ]);

                    foreach ($keys as $key) {
                        if (! in_array($key, $legacyFields, true)) {
                            continue;
                        }

                        $changes[$key] = [
                            'label' => Str::headline($key),
                            'before' => $before[$key] ?? null,
                            'after' => $after[$key] ?? null,
                        ];
                    }
                    foreach ($old['changed_fields'] ?? [] as $key) {
                        if (! in_array($key, $legacyFields, true)) {
                            continue;
                        }

                        $changes[$key] = [
                            'label' => Str::headline($key),
                            'before' => 'Valor não registrado no histórico anterior',
                            'after' => 'Campo alterado',
                        ];
                    }
                    $actor = $row->actor_id ? DB::table('users')->where('id', $row->actor_id)->value('name') : null;
                    $type = $row->entity_type ?? 'permission-catalog';
                    $id = $row->entity_id ?? 0;
                    $accountId = $row->account_id ?? null;
                    $events = ['created' => 'created', 'updated' => 'updated', 'role-updated' => 'updated'];
                    $suffix = Str::afterLast($row->event, '.');
                    $defaultEvent = $table === 'access_catalog_events' ? 'catalog-synchronized' : 'updated';
                    $event = $events[$suffix] ?? $defaultEvent;

                    DB::table('audit_records')->insert([
                        'operation_id' => $row->operation_id,
                        'actor_id' => $row->actor_id,
                        'actor_name' => $actor ?? ($row->actor_id ? 'Usuário #'.$row->actor_id : 'Sistema'),
                        'account_id' => $accountId,
                        'context' => $table === 'access_catalog_events' ? 'system' : 'account',
                        'represented_user_id' => null,
                        'entity_type' => $type,
                        'entity_id' => $id,
                        'entity_name' => (config('audit.entities.'.$type) ?? $type).' #'.$id,
                        'event' => $event,
                        'changes' => json_encode($changes, JSON_THROW_ON_ERROR),
                        'created_at' => $row->created_at,
                        'legacy_key' => $table.':'.$row->id,
                    ]);
                }
            });
        }
        app(SyncPermissionsAction::class)->execute();
        $help = DB::table('permissions')->whereIn('slug', ['self.help.list', 'self.help.view'])->pluck('id')->all();
        foreach (Role::all() as $role) {
            $before = $role->permissions()->pluck('permissions.id')->all();
            $role->permissions()->syncWithoutDetaching($help);
            $after = $role->permissions()->pluck('permissions.id')->all();
            AuditLog::relationship($role, 'permission_ids', $before, $after, 'Permissões');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_records');
    }
};
