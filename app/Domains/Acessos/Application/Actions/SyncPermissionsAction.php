<?php

namespace App\Domains\Acessos\Application\Actions;

use App\Domains\Acessos\Application\Queries\PermissionRegistry;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Plataforma\Domain\Models\Client;

final readonly class SyncPermissionsAction
{
    public function __construct(
        private PermissionRegistry $registry,
        private DefaultRolesAction $defaultRoles,
    ) {}

    /** @return array{created:int, updated:int, deleted:int, total:int} */
    public function execute(bool $prune = false): array
    {
        $definitions = $this->registry->discover();
        if ($prune && ! $definitions) {
            throw new \RuntimeException('Nenhuma permissão descoberta. Remoção cancelada.');
        }
        $created = 0;
        $updated = 0;
        $deleted = 0;

        AuditLog::transaction(function () use ($definitions, $prune, &$created, &$updated, &$deleted): void {
            $changes = [];

            foreach ($definitions as $definition) {
                $permission = Permission::firstOrNew(['slug' => $definition['slug']]);
                $isNew = ! $permission->exists;

                $permission->fill([...$definition, 'is_system' => true, 'account_id' => null]);

                if (! $permission->isDirty()) {
                    continue;
                }

                $changes[$definition['slug']] = [
                    'label' => $definition['name'],
                    'before' => $isNew ? null : $permission->getOriginal('name'),
                    'after' => $definition['name'],
                ];

                $permission->saveOrFail();
                $isNew ? $created++ : $updated++;
            }

            if ($prune) {
                $obsolete = Permission::where('is_system', true)
                    ->whereNotIn('slug', array_column($definitions, 'slug'));

                foreach ((clone $obsolete)->get() as $permission) {
                    $changes[$permission->slug] = [
                        'label' => $permission->name,
                        'before' => $permission->name,
                        'after' => null,
                    ];
                }

                $deleted = $obsolete->delete();
            }

            $accountIds = Client::pluck('id')->all();

            foreach ($accountIds as $accountId) {
                $this->defaultRoles->execute($accountId);
            }

            if ($created || $updated || $deleted) {
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

        return [
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted,
            'total' => count($definitions),
        ];
    }
}
