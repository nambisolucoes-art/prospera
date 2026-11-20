<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $query = DB::table('permissions')->where('is_system', true)->where('module', 'permissions');
            $slugs = $query->pluck('slug')->all();
            if ($slugs === []) {
                return;
            }
            $query->delete();
            DB::table('access_catalog_events')->insert([
                'operation_id' => (string) Str::uuid(),
                'actor_id' => null,
                'scope' => 'platform',
                'event' => 'permission-management.retired',
                'changes' => json_encode([
                    'removed_slugs' => $slugs,
                    'account_ids' => DB::table('clients')->pluck('id')->all(),
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // As definições e seus vínculos não são recriados automaticamente.
    }
};
