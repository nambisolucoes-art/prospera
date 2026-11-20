<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E04 (DA-12): contexto organizacional gravado nos eventos novos.
 *
 * Sem índice: ainda não há consulta por estas colunas. Os candidatos da E08 dependem de plano medido.
 * O histórico anterior permanece como evento de tenant, sem preenchimento retroativo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_records', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('establishment_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('audit_records', function (Blueprint $table) {
            $table->dropColumn(['company_id', 'establishment_id']);
        });
    }
};
