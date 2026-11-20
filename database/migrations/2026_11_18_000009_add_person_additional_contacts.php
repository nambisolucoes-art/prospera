<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->json('additional_phones')->nullable();
            $table->json('additional_emails')->nullable();
        });
        DB::table('people')->update(['additional_phones' => '[]', 'additional_emails' => '[]']);
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn(['additional_phones', 'additional_emails']);
        });
    }
};
