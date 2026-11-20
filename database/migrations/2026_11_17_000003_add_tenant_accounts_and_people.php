<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('plan', 100)->nullable();
            $table->index(['name', 'id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('person_id')->nullable()->unique()->constrained('people')->restrictOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('clients')->restrictOnDelete();
            $table->index('account_id');
            $table->boolean('is_account_admin')->default(false);
        });

        DB::table('users')->select([
            'id',
            'name',
            'email',
            'created_at',
            'updated_at',
        ])->chunkById(500, function ($users) {
            foreach ($users as $user) {
                $id = DB::table('people')->insertGetId([
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ]);

                DB::table('users')->where('id', $user->id)->update(['person_id' => $id]);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
            $table->dropForeign(['account_id']);
            $table->dropUnique(['person_id']);
            $table->dropIndex(['account_id']);
            $table->dropColumn(['person_id', 'account_id', 'is_account_admin']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['name', 'id']);
            $table->dropColumn('plan');
        });

        Schema::dropIfExists('people');
    }
};
