<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('directory_audit_events');
        Schema::dropIfExists('access_catalog_events');
    }

    public function down(): void
    {
        // A reversão recria a estrutura. Os dados originais só voltam com o backup anterior à remoção.
        Schema::create('directory_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_id');
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->foreignId('account_id')->constrained('clients');
            $table->string('event');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->json('changes');
            $table->timestamp('created_at');
        });

        Schema::create('access_catalog_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_id');
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->string('scope')->default('platform');
            $table->string('event');
            $table->json('changes');
            $table->timestamp('created_at');
        });
    }
};
