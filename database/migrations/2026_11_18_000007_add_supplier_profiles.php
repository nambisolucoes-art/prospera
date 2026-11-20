<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('person_id')->unique()->constrained('people')->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->string('person_type', 20);
            $table->string('document', 14)->nullable();
            $table->string('trade_name')->nullable();
            $table->string('state_registration', 30)->nullable();
            $table->string('municipal_registration', 30)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('website')->nullable();
            $table->json('address')->nullable();
            $table->timestamps();
            $table->unique(['account_id', 'document']);
        });
        foreach (DB::table('clients')->pluck('id') as $id) {
            $name = 'Fornecedor';
            $suffix = 1;
            while (DB::table('tags')->where('account_id', $id)->where('module', 'people')->where('name', $name)->exists()) {
                $name = 'Fornecedor (especial '.$suffix++.')';
            }
            DB::table('tags')->insert(['account_id' => $id, 'module' => 'people', 'name' => $name, 'color' => '#7266ba', 'system_key' => 'supplier', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Keep definitions with existing links rather than silently discarding business data.
        DB::table('tags')->where('system_key', 'supplier')->whereNotIn('id', DB::table('person_tag')->select('tag_id'))->delete();
        Schema::dropIfExists('suppliers');
    }
};
