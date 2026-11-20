<?php

namespace App\Console\Commands;

use App\Domains\Acessos\Application\Actions\SyncPermissionsAction;
use Illuminate\Console\Command;

final class SyncPermissionsCommand extends Command
{
    protected $signature = 'permissions:sync {--prune : Remove somente definições do sistema que saíram do código}';

    protected $description = 'Descobre permissões por ação usadas no código e sincroniza o catálogo';

    public function handle(SyncPermissionsAction $sync): int
    {
        $r = $sync->execute((bool) $this->option('prune'));
        $this->info(
            "Permissões sincronizadas: {$r['created']} criadas, {$r['updated']} atualizadas, "
            ."{$r['deleted']} removidas, {$r['total']} descobertas.",
        );

        return self::SUCCESS;
    }
}
