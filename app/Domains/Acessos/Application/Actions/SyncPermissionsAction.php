<?php

namespace App\Domains\Acessos\Application\Actions;

use App\Domains\Acessos\Application\Queries\PermissionRegistry;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SyncPermissionsAction
{
    /** @return array{created:int, updated:int, deleted:int, total:int} */
    public function execute(bool $prune = false): array
    {
        $definitions = app(PermissionRegistry::class)->discover();
        if ($prune && ! $definitions) {
            throw new \RuntimeException('Nenhuma permissão descoberta. Remoção cancelada.');
        }
        $deleted = 0;
        $created = 0;
        $updated = 0;
        AuditLog::transaction(function () use ($definitions, $prune, &$created, &$updated, &$deleted) {
            $changes = [];
            foreach ($definitions as $data) {
                $p = Permission::firstOrNew(['slug' => $data['slug']]);
                $new = ! $p->exists;
                $p->fill([...$data, 'is_system' => true, 'account_id' => null]);
                if ($p->isDirty()) {
                    $changes[$data['slug']] = ['label' => $data['name'], 'before' => $new ? null : $p->getOriginal('name'), 'after' => $data['name']];
                    $p->saveOrFail();
                    $new ? $created++ : $updated++;
                }
            }
            if ($prune) {
                $obsolete = Permission::where('is_system', true)->whereNotIn('slug', array_column($definitions, 'slug'));
                foreach ((clone $obsolete)->get() as $permission) {
                    $changes[$permission->slug] = ['label' => $permission->name, 'before' => $permission->name, 'after' => null];
                }
                $deleted = $obsolete->delete();
            }
            $accountIds = Client::pluck('id')->all();
            foreach ($accountIds as $id) {
                app(DefaultRolesAction::class)->execute($id);
            }
            if ($created || $updated || $deleted) {
                AuditLog::record('permission-catalog', 0, null, 'catalog-synchronized', 'Catálogo de permissões', $changes);
                DB::table('access_catalog_events')->insert(['operation_id' => (string) Str::uuid(), 'actor_id' => auth()->id(), 'scope' => 'platform', 'event' => 'permission-catalog.synchronized', 'changes' => json_encode(['created' => $created, 'updated' => $updated, 'deleted' => $deleted, 'account_ids' => $accountIds, 'slugs' => array_column($definitions, 'slug')], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            }
        });

        return ['created' => $created, 'updated' => $updated, 'deleted' => $deleted, 'total' => count($definitions)];
    }
}
