<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// E09: código público da conta para as URLs /contas/{conta}. Único na plataforma e imutável na v1.
return new class extends Migration
{
    private const RESERVED = ['todos', 'cadastrar', 'editar', 'visualizar', 'selecionar', 'convites', 'inspecao', 'conta', 'contas'];

    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->string('code', 40)->nullable();
        });

        // Contas existentes recebem o código do nome fantasia ou do nome, sem alterar outros dados.
        $taken = [];

        DB::table('clients')->orderBy('id')->select(['id', 'name', 'details'])->chunk(500, function ($accounts) use (&$taken): void {
            foreach ($accounts as $account) {
                $details = json_decode((string) $account->details, true);
                $tradeName = is_array($details) ? trim((string) ($details['trade_name'] ?? '')) : '';
                $base = $this->suggest($tradeName !== '' ? $tradeName : (string) $account->name);
                $code = $base;
                $suffix = 2;

                while (isset($taken[$code])) {
                    $ending = '-'.$suffix;
                    $code = substr($base, 0, 40 - strlen($ending)).$ending;
                    $suffix++;
                }

                $taken[$code] = true;
                DB::table('clients')->where('id', $account->id)->update(['code' => $code]);
            }
        });

        Schema::table('clients', function (Blueprint $table): void {
            $table->string('code', 40)->nullable(false)->change();
            $table->unique('code');
        });

        if (DB::getDriverName() === 'pgsql') {
            $reserved = "'".implode("', '", self::RESERVED)."'";

            DB::statement(
                'alter table clients add constraint clients_code_format '
                ."check (code ~ '^[a-z0-9][a-z0-9-]{1,39}$' and code not in ($reserved))",
            );

            DB::statement(<<<'SQL'
                create function clients_code_immutable() returns trigger language plpgsql as $$
                begin
                    if new.code is distinct from old.code then
                        raise exception 'O código da conta é imutável.' using errcode = '23514';
                    end if;

                    return new;
                end;
                $$
                SQL);

            DB::statement(
                'create trigger clients_code_immutable before update of code on clients '
                .'for each row execute function clients_code_immutable()',
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('drop trigger if exists clients_code_immutable on clients');
            DB::statement('drop function if exists clients_code_immutable()');
            DB::statement('alter table clients drop constraint if exists clients_code_format');
        }

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }

    private function suggest(string $name): string
    {
        $base = trim(Str::limit(Str::slug($name), 36, ''), '-');

        if (strlen($base) < 2 || in_array($base, self::RESERVED, true)) {
            $base = $base === '' ? 'nova-conta' : $base.'-conta';
        }

        return $base;
    }
};
