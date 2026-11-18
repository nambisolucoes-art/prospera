<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('person_type', 10)->nullable();
            $table->string('document', 14)->nullable()->unique();
            $table->boolean('active')->default(true);
            $table->json('details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['document']);
            $table->dropColumn(['person_type', 'document', 'active', 'details']);
        });
    }
};
