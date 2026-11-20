<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_memberships', function (Blueprint $table): void {
            $table->json('workspace_establishments')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('account_memberships', function (Blueprint $table): void {
            $table->dropColumn('workspace_establishments');
        });
    }
};
