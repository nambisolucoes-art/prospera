<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permission_role', fn (Blueprint $table) => $table->index('permission_id'));
        Schema::table('person_tag', fn (Blueprint $table) => $table->index('tag_id'));
        Schema::table('users', fn (Blueprint $table) => $table->index('role_id'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropIndex(['role_id']));
        Schema::table('person_tag', fn (Blueprint $table) => $table->dropIndex(['tag_id']));
        Schema::table('permission_role', fn (Blueprint $table) => $table->dropIndex(['permission_id']));
    }
};
