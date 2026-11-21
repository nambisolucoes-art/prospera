<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * E08: integridade organizacional, identificação histórica da inspeção e consolidação das trilhas legadas.
 *
 * Reversibilidade RP: a reversão remove as colunas novas (nome do representado e referência da inspeção)
 * e as linhas legadas importadas aqui. Os eventos centrais e as tabelas legadas são preservados.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->refuseIncoherentHistory();

        Schema::table('audit_records', function (Blueprint $table) {
            // Nome do usuário representado no momento da operação, sem depender da identidade atual.
            $table->string('represented_user_name')->nullable();

            // Referência da inspeção que produziu o evento (a mesma em início, escritas e encerramento).
            $table->uuid('inspection_id')->nullable();
        });

        Schema::table('establishments', function (Blueprint $table) {
            $table->unique(['id', 'company_id', 'account_id']);
        });

        // Sem cascata: conta, empresa e unidade com histórico não podem ser apagadas fisicamente.
        Schema::table('audit_records', function (Blueprint $table) {
            $table->foreign('account_id')
                ->references('id')
                ->on('clients')
                ->restrictOnDelete();

            $table->foreign(['company_id', 'account_id'])
                ->references(['id', 'account_id'])
                ->on('companies')
                ->restrictOnDelete();

            $table->foreign(['establishment_id', 'company_id', 'account_id'])
                ->references(['id', 'company_id', 'account_id'])
                ->on('establishments')
                ->restrictOnDelete();
        });

        // A FK composta não é verificada quando uma coluna é nula; os CHECKs fecham esses casos no PostgreSQL.
        // No SQLite da suíte, AuditLog::record aplica as mesmas regras antes de gravar.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'alter table audit_records add constraint audit_records_establishment_requires_company '
                .'check (establishment_id is null or company_id is not null)',
            );

            DB::statement(
                'alter table audit_records add constraint audit_records_company_requires_account '
                .'check (company_id is null or account_id is not null)',
            );
        }

        $this->importUncoveredLegacyRows();
    }

    public function down(): void
    {
        DB::table('audit_records')
            ->where('legacy_key', 'like', 'e08:%')
            ->delete();

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table audit_records drop constraint audit_records_establishment_requires_company');
            DB::statement('alter table audit_records drop constraint audit_records_company_requires_account');
        }

        Schema::table('audit_records', function (Blueprint $table) {
            $table->dropForeign(['establishment_id', 'company_id', 'account_id']);
            $table->dropForeign(['company_id', 'account_id']);
            $table->dropForeign(['account_id']);
        });

        Schema::table('establishments', function (Blueprint $table) {
            $table->dropUnique(['id', 'company_id', 'account_id']);
        });

        Schema::table('audit_records', function (Blueprint $table) {
            $table->dropColumn(['represented_user_name', 'inspection_id']);
        });
    }

    // O histórico incoerente é recusado; nada é corrigido por suposição.
    private function refuseIncoherentHistory(): void
    {
        $establishmentWithoutCompany = DB::table('audit_records')
            ->whereNotNull('establishment_id')
            ->whereNull('company_id')
            ->count();

        $companyWithoutAccount = DB::table('audit_records')
            ->whereNotNull('company_id')
            ->whereNull('account_id')
            ->count();

        $unknownAccount = DB::table('audit_records as a')
            ->whereNotNull('a.account_id')
            ->whereNotExists(fn ($clients) => $clients->from('clients as c')->whereColumn('c.id', 'a.account_id'))
            ->count();

        $foreignCompany = DB::table('audit_records as a')
            ->whereNotNull('a.company_id')
            ->whereNotExists(fn ($companies) => $companies->from('companies as c')
                ->whereColumn('c.id', 'a.company_id')
                ->whereColumn('c.account_id', 'a.account_id'))
            ->count();

        $foreignEstablishment = DB::table('audit_records as a')
            ->whereNotNull('a.establishment_id')
            ->whereNotExists(fn ($units) => $units->from('establishments as e')
                ->whereColumn('e.id', 'a.establishment_id')
                ->whereColumn('e.company_id', 'a.company_id')
                ->whereColumn('e.account_id', 'a.account_id'))
            ->count();

        $problems = array_filter([
            'unidade sem empresa' => $establishmentWithoutCompany,
            'empresa sem conta' => $companyWithoutAccount,
            'conta inexistente' => $unknownAccount,
            'empresa de outra conta' => $foreignCompany,
            'unidade de outra empresa ou conta' => $foreignEstablishment,
        ]);

        if ($problems === []) {
            return;
        }

        $summary = [];

        foreach ($problems as $problem => $count) {
            $summary[] = $problem.': '.$count;
        }

        throw new RuntimeException(
            'Há eventos de auditoria com contexto organizacional incoerente ('.implode('; ', $summary).'). '
            .'Revise-os antes de aplicar a integridade da E08; o histórico não é corrigido automaticamente.',
        );
    }

    /**
     * As linhas legadas anteriores à tabela central já foram importadas em 2026_11_18_000004. As posteriores
     * foram gravadas em paralelo com o histórico central; só entram aqui as que não têm evento equivalente.
     */
    private function importUncoveredLegacyRows(): void
    {
        foreach (['directory_audit_events', 'access_catalog_events'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    $imported = DB::table('audit_records')
                        ->whereIn('legacy_key', [$table.':'.$row->id, 'e08:'.$table.':'.$row->id])
                        ->exists();

                    if ($imported) {
                        continue;
                    }

                    $moment = strtotime((string) $row->created_at);
                    $window = [date('Y-m-d H:i:s', $moment - 5), date('Y-m-d H:i:s', $moment + 5)];
                    $equivalent = DB::table('audit_records')
                        ->whereNull('legacy_key')
                        ->whereBetween('created_at', $window);

                    if ($table === 'access_catalog_events') {
                        $equivalent->where('event', 'catalog-synchronized');
                    } else {
                        $equivalent
                            ->where('account_id', $row->account_id)
                            ->where('entity_type', $row->entity_type)
                            ->where('entity_id', $row->entity_id);
                    }

                    if ($equivalent->exists()) {
                        continue;
                    }

                    $old = json_decode((string) $row->changes, true) ?? [];
                    $changes = [];
                    $before = is_array($old['before'] ?? null) ? $old['before'] : [];
                    $after = is_array($old['after'] ?? null) ? $old['after'] : [];

                    // Mesma lista de campos seguros usada na importação original.
                    $fields = [
                        'name',
                        'email',
                        'phone',
                        'module',
                        'color',
                        'system_key',
                        'permission_ids',
                        'tag_ids',
                        'role_id',
                        'person_id',
                        'user_id',
                    ];

                    foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
                        if (! in_array($field, $fields, true)) {
                            continue;
                        }

                        $changes[$field] = [
                            'label' => Str::headline($field),
                            'before' => $before[$field] ?? null,
                            'after' => $after[$field] ?? null,
                        ];
                    }

                    $catalog = $table === 'access_catalog_events';
                    $type = $catalog ? 'permission-catalog' : $row->entity_type;
                    $id = $catalog ? 0 : (int) $row->entity_id;
                    $accountId = $catalog ? null : $row->account_id;
                    $actorName = $row->actor_id
                        ? DB::table('users')->where('id', $row->actor_id)->value('name')
                        : null;
                    $suffix = Str::afterLast((string) $row->event, '.');
                    $event = $catalog ? 'catalog-synchronized' : (in_array($suffix, ['created', 'updated'], true)
                        ? $suffix
                        : 'updated');

                    DB::table('audit_records')->insert([
                        'operation_id' => $row->operation_id,
                        'actor_id' => $row->actor_id,
                        'actor_name' => $actorName ?? ($row->actor_id ? 'Usuário #'.$row->actor_id : 'Sistema'),
                        'account_id' => $accountId,
                        'context' => $catalog ? 'system' : 'account',
                        'entity_type' => $type,
                        'entity_id' => $id,
                        'entity_name' => (config('audit.entities.'.$type) ?? $type).' #'.$id,
                        'event' => $event,
                        'changes' => json_encode($changes, JSON_THROW_ON_ERROR),
                        'created_at' => $row->created_at,
                        'legacy_key' => 'e08:'.$table.':'.$row->id,
                    ]);
                }
            });
        }
    }
};
