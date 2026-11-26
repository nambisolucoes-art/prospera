<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * S07 do plano 002 (PD2-08): fuso IANA por estabelecimento.
 *
 * Reversibilidade R: coluna aditiva com padrão `America/Sao_Paulo`, o mesmo fuso que a S05 usava por configuração
 * para calcular "hoje". Todas as unidades existentes recebem o padrão, então nenhuma vigência de lotação muda ao
 * introduzir a coluna. `starts_on` e `ends_on` continuam datas de calendário, sem conversão. A validade do
 * identificador IANA é do código (a lista vem do PHP); o banco só impede o valor vazio, e só no PostgreSQL, porque o
 * SQLite não aceita CHECK em ALTER TABLE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table): void {
            $table->string('timezone', 64)->default('America/Sao_Paulo');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("alter table establishments add constraint establishments_timezone_check check (timezone <> '')");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table establishments drop constraint establishments_timezone_check');
        }

        Schema::table('establishments', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });
    }
};
