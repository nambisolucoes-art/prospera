<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * E07 (seção 10.2 do plano 001): clientes e fornecedores passam a ser relações comerciais por empresa.
 *
 * Cada relação existente recebe a empresa única da sua conta, que equivale ao estado anterior. Uma conta com
 * relações e mais de uma empresa não tem empresa determinável: a migração para com o relatório, sem escolher
 * uma empresa. As linhas Cliente e Fornecedor de person_tag são substituídas pela derivação das relações.
 *
 * Reversão (23.1): sem perda enquanto cada pessoa tiver no máximo uma relação de cada tipo. Depois disso, a
 * down() recusa e a recuperação é por backup ou correção para frente.
 */
return new class extends Migration
{
    private const TABLES = ['customers' => 'customer', 'suppliers' => 'supplier'];

    public function up(): void
    {
        $this->assertCompaniesAreDeterminable();
        $this->assertTagsMatchRelations();

        foreach (array_keys(self::TABLES) as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unsignedBigInteger('company_id')->nullable()->after('account_id');
            });

            // Nesta etapa, toda conta com relações tem exatamente uma empresa (verificado acima).
            DB::table($table)->update([
                'company_id' => DB::raw(
                    '(select companies.id from companies where companies.account_id = '.$table.'.account_id)',
                ),
            ]);

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unsignedBigInteger('company_id')->nullable(false)->change();
                $blueprint->dropUnique(['person_id']);
                $blueprint->dropForeign(['person_id']);

                $blueprint->foreign(['company_id', 'account_id'])
                    ->references(['id', 'account_id'])
                    ->on('companies')
                    ->restrictOnDelete();

                $blueprint->foreign(['person_id', 'account_id'])
                    ->references(['id', 'account_id'])
                    ->on('people')
                    ->restrictOnDelete();

                // Uma relação de cada tipo por pessoa e empresa; também atende a listagem por empresa.
                $blueprint->unique(['company_id', 'person_id']);

                // Relações de uma pessoa: ficha, etiquetas derivadas da página e regra de edição do cadastro.
                $blueprint->index('person_id');
            });
        }

        DB::table('person_tag')
            ->whereIn('tag_id', DB::table('tags')->whereIn('system_key', self::TABLES)->select('id'))
            ->delete();
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => $systemKey) {
            $repeated = DB::table($table)
                ->select('person_id')
                ->groupBy('person_id')
                ->havingRaw('count(*) > 1')
                ->limit(20)
                ->pluck('person_id');

            if ($repeated->isNotEmpty()) {
                throw new RuntimeException(
                    "Há pessoas com relação de {$systemKey} em mais de uma empresa (pessoas {$repeated->implode(', ')}). "
                    .'A reversão perderia relações: restaure o backup ou corrija para frente.',
                );
            }
        }

        foreach (self::TABLES as $table => $systemKey) {
            $active = DB::table($table.' as r')
                ->join('tags as t', function ($join) use ($systemKey): void {
                    $join->on('t.account_id', '=', 'r.account_id')
                        ->where('t.module', 'people')
                        ->where('t.system_key', $systemKey);
                })
                ->where('r.active', true)
                ->get(['r.person_id', 't.id as tag_id']);

            foreach ($active as $row) {
                DB::table('person_tag')->insertOrIgnore([
                    'person_id' => $row->person_id,
                    'tag_id' => $row->tag_id,
                ]);
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropIndex(['person_id']);
                $blueprint->dropUnique(['company_id', 'person_id']);
                $blueprint->dropForeign(['person_id', 'account_id']);
                $blueprint->dropForeign(['company_id', 'account_id']);
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('company_id');
                $blueprint->unique('person_id');
                $blueprint->foreign('person_id')->references('id')->on('people')->restrictOnDelete();
            });
        }
    }

    private function assertCompaniesAreDeterminable(): void
    {
        $accounts = collect();

        foreach (array_keys(self::TABLES) as $table) {
            $accounts = $accounts->merge(DB::table($table)->distinct()->pluck('account_id'));
        }

        $ambiguous = DB::table('companies')
            ->whereIn('account_id', $accounts->unique()->values())
            ->select('account_id')
            ->groupBy('account_id')
            ->havingRaw('count(*) <> 1')
            ->pluck('account_id');

        $withoutCompany = $accounts->unique()->diff(DB::table('companies')->distinct()->pluck('account_id'));

        if ($ambiguous->isNotEmpty() || $withoutCompany->isNotEmpty()) {
            throw new RuntimeException(
                'A empresa das relações comerciais não pode ser determinada nas contas '
                .$ambiguous->merge($withoutCompany)->unique()->sort()->implode(', ')
                .'. Cada uma deve ter exatamente uma empresa antes desta migração. '
                .'Nenhuma empresa é escolhida automaticamente.',
            );
        }
    }

    // A etiqueta nunca foi fonte da relação: uma etiqueta sem relação ativa indica dado incoerente.
    private function assertTagsMatchRelations(): void
    {
        foreach (self::TABLES as $table => $systemKey) {
            $orphans = DB::table('person_tag as pt')
                ->join('tags as t', 't.id', '=', 'pt.tag_id')
                ->where('t.system_key', $systemKey)
                ->whereNotExists(fn ($relation) => $relation
                    ->from($table.' as r')
                    ->whereColumn('r.person_id', 'pt.person_id')
                    ->whereColumn('r.account_id', 't.account_id')
                    ->where('r.active', true))
                ->limit(20)
                ->pluck('pt.person_id');

            if ($orphans->isNotEmpty()) {
                throw new RuntimeException(
                    "A etiqueta {$systemKey} está em pessoas sem relação ativa ({$orphans->implode(', ')}). "
                    .'Revise esses cadastros antes de migrar.',
                );
            }
        }
    }
};
