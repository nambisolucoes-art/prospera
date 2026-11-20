<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $t) {
            $t->foreignId('account_id')->nullable()->constrained('clients')->restrictOnDelete();
            $t->string('phone', 30)->nullable();
        });
        foreach (DB::table('users')->whereNotNull('account_id')->get() as $u) {
            DB::table('people')->where('id', $u->person_id)->update(['account_id' => $u->account_id]);
        }
        Schema::create('tags', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained('clients')->restrictOnDelete();
            $t->string('module', 40);
            $t->string('name', 100);
            $t->string('color', 7)->default('#3563e9');
            $t->string('system_key')->nullable();
            $t->timestamps();
            $t->unique(['account_id', 'module', 'name']);
            $t->unique(['account_id', 'module', 'system_key']);
        });
        Schema::create('person_tag', function (Blueprint $t) {
            $t->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $t->foreignId('tag_id')->constrained('tags')->restrictOnDelete();
            $t->primary(['person_id', 'tag_id']);
        });
        Schema::create('directory_audit_events', function (Blueprint $t) {
            $t->id();
            $t->uuid('operation_id');
            $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->foreignId('account_id')->constrained('clients');
            $t->string('event');
            $t->string('entity_type');
            $t->unsignedBigInteger('entity_id');
            $t->json('changes');
            $t->timestamp('created_at');
        });
        foreach (DB::table('clients')->pluck('id') as $id) {
            DB::table('tags')->insert([
                'account_id' => $id,
                'module' => 'people',
                'name' => 'Usuário',
                'system_key' => 'user',
                'color' => '#3563e9',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        foreach (DB::table('users')->whereNotNull('account_id')->get() as $u) {
            $tag = DB::table('tags')->where('account_id', $u->account_id)->where('system_key', 'user')->value('id');
            DB::table('person_tag')->insert(['person_id' => $u->person_id, 'tag_id' => $tag]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('directory_audit_events');
        Schema::dropIfExists('person_tag');
        Schema::dropIfExists('tags');
        Schema::table('people', function (Blueprint $t) {
            $t->dropConstrainedForeignId('account_id');
            $t->dropColumn('phone');
        });
    }
};
