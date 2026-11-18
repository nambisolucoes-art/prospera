<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrepareSqlite extends Command
{
    protected $signature = 'db:prepare-sqlite';

    protected $description = 'Prepara um banco SQLite independente para execução local';

    public function handle(): int
    {
        $database = storage_path('app/prospera.sqlite');
        if (! is_file($database) && ! touch($database)) {
            $this->error('Não foi possível criar o arquivo SQLite.');

            return self::FAILURE;
        }

        config(['database.connections.sqlite.database' => $database, 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');

        $status = $this->call('migrate', ['--database' => 'sqlite', '--force' => true]);
        if ($status === self::SUCCESS) {
            $this->info($database);
        }

        return $status;
    }
}
