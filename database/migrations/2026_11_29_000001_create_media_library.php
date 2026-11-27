<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * S08 do plano 002: biblioteca de mídia (`media_assets` e `media_variants`).
 *
 * Reversibilidade R: tabelas novas e vazias. Toda mídia pertence a uma conta e a uma empresa; `establishment_id` nulo é a
 * biblioteca corporativa e preenchido é a mídia local da unidade. As FKs são compostas (empresa na conta, unidade na
 * empresa, variante no ativo da mesma empresa), então uma linha nunca aponta para outra empresa. O original fica em
 * disco privado (`original_path` opaco) e cada derivado tem `public_path` opaco. Os CHECKs entram no SQL de criação
 * porque o SQLite só os aceita aí.
 */
return new class extends Migration
{
    public function up(): void
    {
        $assets = new Blueprint(DB::connection(), 'media_assets', function (Blueprint $table): void {
            $table->create();
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('establishment_id')->nullable();
            $table->string('purpose', 20);
            $table->string('original_path', 120);
            $table->string('mime', 20);
            $table->unsignedBigInteger('bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('sha256', 64);
            $table->string('alt_text', 300)->nullable();
            $table->string('license_note', 300)->nullable();
            $table->string('status', 20)->default('ready');
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('clients')->restrictOnDelete();
            $table->foreign(['company_id', 'account_id'])
                ->references(['id', 'account_id'])->on('companies')->restrictOnDelete();
            $table->foreign(['establishment_id', 'company_id'])
                ->references(['id', 'company_id'])->on('establishments')->restrictOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->restrictOnDelete();

            $table->unique(['id', 'company_id']);
            $table->unique('original_path');
            $table->index(['company_id', 'establishment_id', 'created_at'], 'media_assets_owner_created_index');
            $table->index(['company_id', 'sha256'], 'media_assets_company_sha256_index');
        });

        $this->createWithChecks($assets, [
            "purpose in ('logo', 'banner', 'gallery', 'employee_photo', 'og_image')",
            "status in ('ready', 'removal_requested')",
            "mime in ('image/jpeg', 'image/png', 'image/webp')",
            'bytes > 0 and width > 0 and height > 0',
            'length(sha256) = 64',
        ]);

        $variants = new Blueprint(DB::connection(), 'media_variants', function (Blueprint $table): void {
            $table->create();
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('media_asset_id');
            $table->string('kind', 12);
            $table->string('public_path', 120);
            $table->string('mime', 20);
            $table->unsignedBigInteger('bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('clients')->restrictOnDelete();
            $table->foreign(['company_id', 'account_id'])
                ->references(['id', 'account_id'])->on('companies')->restrictOnDelete();
            $table->foreign(['media_asset_id', 'company_id'])
                ->references(['id', 'company_id'])->on('media_assets')->restrictOnDelete();

            $table->unique(['media_asset_id', 'kind']);
            $table->unique('public_path');
        });

        $this->createWithChecks($variants, [
            "mime in ('image/jpeg', 'image/png', 'image/webp')",
            'bytes > 0 and width > 0 and height > 0',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('media_variants');
        Schema::dropIfExists('media_assets');
    }

    /** @param list<string> $checks */
    private function createWithChecks(Blueprint $table, array $checks): void
    {
        $statements = $table->toSql();
        $statements[0] = substr($statements[0], 0, -1);

        foreach ($checks as $index => $check) {
            $statements[0] .= ', constraint '.$table->getTable().'_check_'.($index + 1).' check ('.$check.')';
        }

        $statements[0] .= ')';

        foreach ($statements as $statement) {
            DB::statement($statement);
        }
    }
};
