<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * S07 do plano 002: exceções de horário de funcionamento por empresa ou estabelecimento (seção 09.5).
 *
 * Reversibilidade R: tabela nova e vazia. Datas são colunas `date` (calendário), nunca instantes. Os CHECKs entram no
 * SQL de criação porque o SQLite só os aceita aí:
 * - tipo conhecido e intervalo `ends_on >= starts_on`;
 * - `special_hours` exige turnos e os demais tipos não os têm;
 * - `applies_to_overrides` só existe em exceção de empresa (PD2-08) e nasce falso.
 * A unicidade de períodos sem sobreposição por dono é regra da Action, serializada pelo bloqueio da empresa, e a
 * leitura continua determinística mesmo assim (menor período, depois o menor id). `establishments (id, company_id)`
 * já é alvo de FK composta desde a E04.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = new Blueprint(DB::connection(), 'hour_exceptions', function (Blueprint $table): void {
            $table->create();
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('establishment_id')->nullable();
            $table->string('kind', 20);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->json('shifts')->nullable();
            $table->string('note', 160)->nullable();
            $table->boolean('applies_to_overrides')->default(false);
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('clients')->restrictOnDelete();
            $table->foreign(['company_id', 'account_id'])
                ->references(['id', 'account_id'])->on('companies')->restrictOnDelete();
            $table->foreign(['establishment_id', 'company_id'])
                ->references(['id', 'company_id'])->on('establishments')->restrictOnDelete();

            $table->index(['company_id', 'establishment_id', 'starts_on'], 'hour_exceptions_owner_period_index');
        });

        $checks = [
            "kind in ('holiday', 'temporary_closure', 'special_hours')",
            'ends_on >= starts_on',
            "(kind = 'special_hours' and shifts is not null) or (kind <> 'special_hours' and shifts is null)",
            'establishment_id is null or applies_to_overrides = '.(DB::getDriverName() === 'pgsql' ? 'false' : '0'),
        ];

        $statements = $table->toSql();
        $statements[0] = substr($statements[0], 0, -1);

        foreach ($checks as $index => $check) {
            $statements[0] .= ', constraint hour_exceptions_check_'.($index + 1).' check ('.$check.')';
        }

        $statements[0] .= ')';

        foreach ($statements as $statement) {
            DB::statement($statement);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hour_exceptions');
    }
};
