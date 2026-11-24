<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Registros existentes ficam com marcador nulo, sem reescrita da tabela.
        DB::statement('alter table audit_records add column write_transaction xid8');
        DB::statement('alter table audit_records alter column write_transaction set default pg_current_xact_id()');

        DB::unprepared(<<<'SQL'
            create or replace function audit_records_protect_history() returns trigger
            language plpgsql as $$
            begin
                if TG_OP = 'TRUNCATE' then
                    raise exception 'O histórico de auditoria é imutável' using errcode = '55000';
                end if;

                if OLD.write_transaction is distinct from pg_current_xact_id() then
                    raise exception 'O histórico de auditoria é imutável' using errcode = '55000';
                end if;

                if TG_OP = 'DELETE' then
                    return OLD;
                end if;

                NEW.write_transaction := OLD.write_transaction;
                return NEW;
            end
            $$;

            create trigger audit_records_protect_rows
            before update or delete on audit_records
            for each row execute function audit_records_protect_history();

            create trigger audit_records_protect_truncate
            before truncate on audit_records
            for each statement execute function audit_records_protect_history();
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            drop trigger audit_records_protect_truncate on audit_records;
            drop trigger audit_records_protect_rows on audit_records;
            drop function audit_records_protect_history();
            alter table audit_records drop column write_transaction;
            SQL);
    }
};
