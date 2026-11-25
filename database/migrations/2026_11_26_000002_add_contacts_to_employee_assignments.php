<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * S05 (complemento): contatos profissionais próprios da lotação. `contact_mode = inherit` usa os da relação
 * (`employees.work_*`) e acompanha as mudanças dela; `override` usa `work_contacts`, inclusive vazio intencional.
 *
 * Reversibilidade R: coluna aditiva com padrão `inherit`, então toda lotação existente continua herdando. O SQLite
 * não aceita CHECK em ALTER TABLE; no PostgreSQL a coerência entre modo e valor também é garantida pelo banco, e nos
 * dois bancos pelo Model, pela Action e pelo `institutional:check`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_assignments', function (Blueprint $table): void {
            $table->string('contact_mode', 10)->default('inherit');
            $table->json('work_contacts')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table employee_assignments add constraint employee_assignments_contacts_check check '
                ."((contact_mode = 'inherit' and work_contacts is null) or (contact_mode = 'override' and work_contacts is not null))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table employee_assignments drop constraint employee_assignments_contacts_check');
        }

        Schema::table('employee_assignments', function (Blueprint $table): void {
            $table->dropColumn(['contact_mode', 'work_contacts']);
        });
    }
};
