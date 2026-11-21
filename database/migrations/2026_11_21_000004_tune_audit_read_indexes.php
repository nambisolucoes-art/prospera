<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E08: índices de leitura da auditoria justificados pelas medições em 10 mil a 1 milhão de eventos.
 *
 * - (actor_id, created_at) e (entity_type, event) não começavam pela conta e não serviam à consulta com escopo.
 *   Passam a começar por account_id: filtro de autor e varredura solta das opções de autor, tipo e ação.
 * - (company_id, created_at, id) e (establishment_id, created_at, id): página, contagem e opções dos auditores de
 *   empresa e de unidade, e os filtros de empresa e de estabelecimento.
 *
 * Reversibilidade R: só índices, sem dados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_records', function (Blueprint $table) {
            $table->dropIndex(['actor_id', 'created_at']);
            $table->dropIndex(['entity_type', 'event']);

            $table->index(['account_id', 'actor_id', 'created_at']);
            $table->index(['account_id', 'entity_type', 'event']);
            $table->index(['company_id', 'created_at', 'id']);
            $table->index(['establishment_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_records', function (Blueprint $table) {
            $table->dropIndex(['establishment_id', 'created_at', 'id']);
            $table->dropIndex(['company_id', 'created_at', 'id']);
            $table->dropIndex(['account_id', 'entity_type', 'event']);
            $table->dropIndex(['account_id', 'actor_id', 'created_at']);

            $table->index(['entity_type', 'event']);
            $table->index(['actor_id', 'created_at']);
        });
    }
};
