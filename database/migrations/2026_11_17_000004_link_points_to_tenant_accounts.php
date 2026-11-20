<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('points', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->constrained()->restrictOnDelete();
            $table->index('client_id');
            $table->unsignedBigInteger('establishment_id')->nullable()->change();
        });

        DB::table('points')->update([
            'client_id' => DB::table('establishments')
                ->select('client_id')
                ->whereColumn('establishments.id', 'points.establishment_id'),
        ]);

        Schema::table('points', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->change();
        });
    }

    public function down(): void
    {
        $accountsWithLegacyPoints = DB::table('points')
            ->whereNull('establishment_id')
            ->select('client_id');

        $accounts = DB::table('clients')->whereIn('id', $accountsWithLegacyPoints);

        foreach ($accounts->select('id', 'name')->lazyById() as $account) {
            $legacyId = DB::table('establishments')->where('client_id', $account->id)->orderBy('id')->value('id')
                ?? DB::table('establishments')->insertGetId([
                    'client_id' => $account->id,
                    'name' => $account->name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            DB::table('points')
                ->where('client_id', $account->id)
                ->whereNull('establishment_id')
                ->update(['establishment_id' => $legacyId]);
        }

        Schema::table('points', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->unsignedBigInteger('establishment_id')->change();
        });
    }
};
