<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', fn (Blueprint $table) => $table->unique(['id', 'account_id']));

        foreach (['customers', 'suppliers'] as $relations) {
            Schema::table($relations, fn (Blueprint $table) => $table->unique(['id', 'company_id', 'account_id']));

            Schema::create($relations.'_tags', function (Blueprint $table) use ($relations): void {
                $table->foreignId('relation_id');
                $table->foreignId('company_id');
                $table->foreignId('account_id');
                $table->foreignId('tag_id');
                $table->primary(['relation_id', 'tag_id']);
                $table->index('tag_id');
                $table->foreign(['relation_id', 'company_id', 'account_id'])
                    ->references(['id', 'company_id', 'account_id'])->on($relations)->cascadeOnDelete();
                $table->foreign(['tag_id', 'account_id'])
                    ->references(['id', 'account_id'])->on('tags')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['customers', 'suppliers'] as $relations) {
            Schema::dropIfExists($relations.'_tags');
            Schema::table($relations, fn (Blueprint $table) => $table->dropUnique(['id', 'company_id', 'account_id']));
        }

        Schema::table('tags', fn (Blueprint $table) => $table->dropUnique(['id', 'account_id']));
    }
};
