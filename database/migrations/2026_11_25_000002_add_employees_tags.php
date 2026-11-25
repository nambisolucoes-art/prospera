<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Etiquetas convencionais do módulo Colaboradores, no molde de customers_tags e suppliers_tags: a seleção pertence
 * ao vínculo da empresa, nunca à Pessoa. Reversibilidade R: a tabela nasce vazia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', fn (Blueprint $table) => $table->unique(['id', 'company_id', 'account_id']));

        Schema::create('employees_tags', function (Blueprint $table): void {
            $table->foreignId('relation_id');
            $table->foreignId('company_id');
            $table->foreignId('account_id');
            $table->foreignId('tag_id');
            $table->primary(['relation_id', 'tag_id']);
            $table->index('tag_id');
            $table->foreign(['relation_id', 'company_id', 'account_id'])
                ->references(['id', 'company_id', 'account_id'])->on('employees')->cascadeOnDelete();
            $table->foreign(['tag_id', 'account_id'])
                ->references(['id', 'account_id'])->on('tags')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees_tags');
        Schema::table('employees', fn (Blueprint $table) => $table->dropUnique(['id', 'company_id', 'account_id']));
    }
};
