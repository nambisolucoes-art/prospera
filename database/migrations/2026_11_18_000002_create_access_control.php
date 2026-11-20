<?php

use App\Domains\Acessos\Application\Actions\SyncPermissionsAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('name');
            $t->string('scope', 20);
            $t->string('module', 80);
            $t->foreignId('account_id')->nullable()->constrained('clients')->restrictOnDelete();
            $t->boolean('is_system')->default(false);
            $t->timestamps();
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained('clients')->restrictOnDelete();
            $t->string('name', 100);
            $t->string('system_key')->nullable();
            $t->timestamps();
            $t->unique(['account_id', 'name']);
            $t->unique(['account_id', 'system_key']);
        });
        Schema::create('permission_role', function (Blueprint $t) {
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $t->primary(['role_id', 'permission_id']);
        });
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('role_id')->nullable()->constrained('roles')->restrictOnDelete();
        });
        Schema::create('access_catalog_events', function (Blueprint $t) {
            $t->id();
            $t->uuid('operation_id');
            $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->string('scope')->default('platform');
            $t->string('event');
            $t->json('changes');
            $t->timestamp('created_at');
        });
        app(SyncPermissionsAction::class)->execute();
        foreach (DB::table('users')->whereNotNull('account_id')->get() as $u) {
            $key = $u->is_account_admin ? 'administrator' : 'member';
            $id = DB::table('roles')->where('account_id', $u->account_id)->where('system_key', $key)->value('id');
            DB::table('users')->where('id', $u->id)->update(['role_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('access_catalog_events');
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('role_id'));
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
