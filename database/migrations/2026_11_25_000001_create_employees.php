<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * S04 do plano 002: colaborador é um papel de Pessoa por empresa (classe empresa), no molde de customers.
 *
 * Reversibilidade R: a tabela nasce vazia. A etiqueta especial `employee` é criada de forma idempotente por
 * `system_key` nas contas existentes, e a down() remove as etiquetas criadas que nenhuma pessoa usa.
 * O alvo único (id, company_id) é para as FKs compostas da lotação (S05). O índice em person_id atende a
 * ficha da pessoa e a regra de edição do cadastro central.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained('clients')->restrictOnDelete();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('person_id');
            $table->boolean('active')->default(true);
            $table->string('work_email')->nullable();
            $table->string('work_phone', 40)->nullable();
            $table->string('work_whatsapp', 40)->nullable();
            $table->timestamps();

            $table->foreign(['company_id', 'account_id'])
                ->references(['id', 'account_id'])
                ->on('companies')
                ->restrictOnDelete();

            $table->foreign(['person_id', 'account_id'])
                ->references(['id', 'account_id'])
                ->on('people')
                ->restrictOnDelete();

            $table->unique(['company_id', 'person_id']);
            $table->unique(['id', 'company_id']);
            $table->index('person_id');
        });

        foreach (DB::table('clients')->pluck('id') as $accountId) {
            $exists = DB::table('tags')
                ->where('account_id', $accountId)
                ->where('module', 'people')
                ->where('system_key', 'employee')
                ->exists();

            if ($exists) {
                continue;
            }

            $name = 'Colaborador';
            $suffix = 1;

            while (
                DB::table('tags')
                    ->where('account_id', $accountId)
                    ->where('module', 'people')
                    ->where('name', $name)
                    ->exists()
            ) {
                $name = 'Colaborador (especial '.$suffix++.')';
            }

            DB::table('tags')->insert([
                'account_id' => $accountId,
                'module' => 'people',
                'name' => $name,
                'color' => '#1abc9c',
                'system_key' => 'employee',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('tags')
            ->where('system_key', 'employee')
            ->whereNotIn('id', DB::table('person_tag')->select('tag_id'))
            ->delete();

        Schema::dropIfExists('employees');
    }
};
