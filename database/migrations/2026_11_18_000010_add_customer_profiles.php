<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('person_id')->unique()->constrained('people')->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        foreach (DB::table('clients')->pluck('id') as $id) {
            $name = 'Cliente';
            $suffix = 1;
            while (DB::table('tags')->where('account_id', $id)->where('module', 'people')->where('name', $name)->exists()) {
                $name = 'Cliente (especial '.$suffix++.')';
            }
            DB::table('tags')->insert(['account_id' => $id, 'module' => 'people', 'name' => $name, 'color' => '#23b7e5', 'system_key' => 'customer', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('tags')->where('system_key', 'customer')->whereNotIn('id', DB::table('person_tag')->select('tag_id'))->delete();
        Schema::dropIfExists('customers');
    }
};
