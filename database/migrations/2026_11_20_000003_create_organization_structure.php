<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * E04: empresas e estabelecimentos (seções 6.6, 16.3 e 16.4 do plano 001).
 *
 * A tabela establishments anterior (client_id e name) nunca recebeu regra de negócio e é substituída.
 * Reversível sem perda enquanto cada conta tiver somente a empresa e a unidade provisionadas; depois disso,
 * a reversão é recusada e a recuperação é por backup (seção 23.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        $legacy = DB::table('establishments')->count();

        if ($legacy > 0) {
            throw new RuntimeException(
                "A tabela establishments anterior contém {$legacy} registros. Revise-os antes de migrar.",
            );
        }

        Schema::drop('establishments');

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('clients')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('trade_name')->nullable();
            $table->string('person_type', 10)->nullable();
            $table->string('tax_root', 8)->nullable();
            $table->string('individual_document', 11)->nullable();
            $table->string('tax_regime', 30)->nullable();
            $table->string('main_cnae', 7)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            // Alvo da FK composta dos estabelecimentos.
            $table->unique(['id', 'account_id']);
            $table->unique(['account_id', 'code']);
        });

        DB::statement(
            'create unique index companies_account_id_tax_root_unique on companies (account_id, tax_root) '
            .'where tax_root is not null',
        );

        DB::statement(
            'create unique index companies_account_id_individual_document_unique '
            .'on companies (account_id, individual_document) where individual_document is not null',
        );

        $establishments = new Blueprint(DB::connection(), 'establishments', function (Blueprint $table) {
            $table->create();
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('company_id');
            $table->string('code', 40);
            $table->string('name');
            $table->string('trade_name')->nullable();
            $table->string('tax_document', 14)->nullable();
            $table->string('state_registration', 30)->nullable();
            $table->string('municipal_registration', 30)->nullable();
            $table->boolean('is_headquarters')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->boolean('active')->default(true);
            $table->json('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('email')->nullable();
            $table->json('additional_phones')->nullable();
            $table->json('additional_emails')->nullable();
            $table->timestamps();

            $table->foreign(['company_id', 'account_id'])
                ->references(['id', 'account_id'])
                ->on('companies')
                ->restrictOnDelete();

            $table->unique(['account_id', 'code']);
            $table->index(['company_id', 'active', 'name']);
        });

        // O SQLite só aceita CHECK na criação da tabela, por isso a restrição entra no SQL gerado.
        $statements = $establishments->toSql();
        $statements[0] = substr($statements[0], 0, -1)
            .', constraint establishments_primary_active_check check (not is_primary or active))';

        foreach ($statements as $statement) {
            DB::statement($statement);
        }

        DB::statement(
            'create unique index establishments_company_id_primary_unique on establishments (company_id) '
            .'where is_primary',
        );

        DB::statement(
            'create unique index establishments_company_id_headquarters_unique on establishments (company_id) '
            .'where is_headquarters',
        );

        DB::statement(
            'create unique index establishments_account_id_tax_document_unique '
            .'on establishments (account_id, tax_document) where tax_document is not null',
        );
    }

    public function down(): void
    {
        $extraCompanies = DB::table('companies')
            ->select('account_id')
            ->groupBy('account_id')
            ->havingRaw('count(*) > 1')
            ->exists();

        $extraEstablishments = DB::table('establishments')
            ->select('company_id')
            ->groupBy('company_id')
            ->havingRaw('count(*) > 1')
            ->exists();

        if ($extraCompanies || $extraEstablishments) {
            throw new RuntimeException(
                'Há empresas ou estabelecimentos cadastrados além do provisionamento. '
                .'A reversão perderia dados: restaure o backup ou corrija para frente.',
            );
        }

        Schema::drop('establishments');
        Schema::drop('companies');

        Schema::create('establishments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->index('client_id');
            $table->string('name');
            $table->timestamps();
        });
    }
};
