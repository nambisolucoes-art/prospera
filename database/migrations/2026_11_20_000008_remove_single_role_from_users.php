<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['role_id']);
            $table->dropUnique(['person_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['role_id']);
            $table->dropForeign(['person_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['role_id', 'is_account_admin', 'person_id']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Use uma cópia anterior à migração para recuperar as colunas removidas.');
    }
};
