<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * S05 do plano 002: lotação do colaborador, um período por estabelecimento (classe empresa, com estabelecimento).
 *
 * Reversibilidade R: a tabela nasce vazia. As FKs compostas mantêm colaborador, estabelecimento, departamento e função
 * na mesma empresa e conta. A sobreposição de períodos e a coerência da principal dependem de várias linhas, então
 * ficam na regra de domínio (transação com trava do colaborador) e no `institutional:check`. O índice parcial garante
 * uma só principal sem data final por colaborador, nos dois bancos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $assignments = new Blueprint(DB::connection(), 'employee_assignments', function (Blueprint $table): void {
            $table->create();
            $table->id();
            $table->foreignId('account_id')->constrained('clients')->restrictOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('establishment_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('job_title_id')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign(['company_id', 'account_id'])
                ->references(['id', 'account_id'])->on('companies')->restrictOnDelete();
            $table->foreign(['employee_id', 'company_id'])
                ->references(['id', 'company_id'])->on('employees')->restrictOnDelete();
            $table->foreign(['establishment_id', 'company_id'])
                ->references(['id', 'company_id'])->on('establishments')->restrictOnDelete();
            $table->foreign(['department_id', 'company_id'])
                ->references(['id', 'company_id'])->on('departments')->restrictOnDelete();
            $table->foreign(['job_title_id', 'company_id'])
                ->references(['id', 'company_id'])->on('job_titles')->restrictOnDelete();

            $table->index(['employee_id', 'establishment_id']);
            $table->index(['establishment_id', 'ends_on']);
        });

        // O SQLite só aceita CHECK na criação da tabela, por isso a restrição entra no SQL gerado.
        $statements = $assignments->toSql();
        $statements[0] = substr($statements[0], 0, -1)
            .', constraint employee_assignments_period_check check (ends_on is null or ends_on >= starts_on))';

        foreach ($statements as $statement) {
            DB::statement($statement);
        }

        DB::statement('create unique index employee_assignments_open_primary_unique on employee_assignments '
            .'(employee_id) where is_primary = true and ends_on is null');
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_assignments');
    }
};
