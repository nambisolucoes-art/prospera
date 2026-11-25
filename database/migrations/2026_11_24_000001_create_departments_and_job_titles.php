<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * S03 do plano 002: departamentos e funções são cadastros da empresa (classe empresa).
 *
 * Reversibilidade R: as tabelas nascem vazias e só são removidas enquanto não houver uso real.
 * `normalized_name` é derivado do nome (sem acentos, caixa e espaços repetidos) e garante a unicidade por empresa
 * no banco. O alvo único (id, company_id) existe para as FKs compostas da lotação (S05).
 * Sem índice além das unicidades: o catálogo de uma empresa é pequeno e as consultas começam por company_id.
 */
return new class extends Migration
{
    private const TABLES = ['departments', 'job_titles'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
                $table->foreignId('account_id')->constrained('clients')->restrictOnDelete();
                $table->unsignedBigInteger('company_id');
                $table->string('name', 100);
                $table->string('normalized_name', 100);
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->foreign(['company_id', 'account_id'])
                    ->references(['id', 'account_id'])
                    ->on('companies')
                    ->restrictOnDelete();

                $table->unique(['id', 'company_id']);
                $table->unique(['company_id', 'normalized_name']);
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $name) {
            Schema::dropIfExists($name);
        }
    }
};
