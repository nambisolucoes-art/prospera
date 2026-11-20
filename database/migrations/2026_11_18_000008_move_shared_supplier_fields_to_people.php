<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FIELDS = ['person_type', 'document', 'trade_name', 'state_registration', 'municipal_registration', 'contact_name', 'whatsapp', 'website', 'address'];

    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $this->fields($table);
            $table->unique(['account_id', 'document']);
        });
        foreach (DB::table('suppliers')->get() as $supplier) {
            $values = [];
            foreach (self::FIELDS as $field) {
                $values[$field] = $supplier->{$field};
            }
            DB::table('people')->where('id', $supplier->person_id)->where('account_id', $supplier->account_id)->update($values);
        }
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique(['account_id', 'document']);
            $table->dropColumn(self::FIELDS);
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $this->fields($table);
            $table->unique(['account_id', 'document']);
        });
        foreach (DB::table('suppliers')->get() as $supplier) {
            $person = DB::table('people')->where('id', $supplier->person_id)->where('account_id', $supplier->account_id)->first();
            if (! $person) {
                continue;
            }
            $values = [];
            foreach (self::FIELDS as $field) {
                $values[$field] = $person->{$field};
            }
            DB::table('suppliers')->where('id', $supplier->id)->update($values);
        }
        Schema::table('people', function (Blueprint $table) {
            $table->dropUnique(['account_id', 'document']);
            $table->dropColumn(self::FIELDS);
        });
    }

    private function fields(Blueprint $table): void
    {
        $table->string('person_type', 20)->default('individual');
        $table->string('document', 14)->nullable();
        $table->string('trade_name')->nullable();
        $table->string('state_registration', 30)->nullable();
        $table->string('municipal_registration', 30)->nullable();
        $table->string('contact_name')->nullable();
        $table->string('whatsapp', 30)->nullable();
        $table->string('website')->nullable();
        $table->json('address')->nullable();
    }
};
