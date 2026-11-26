<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * S06 do plano 002: seções do perfil institucional, com herança por seção inteira e par de trabalho e publicado.
 *
 * Reversibilidade R: tabelas e coluna nascem vazias e aditivas (`show_public_address` desligado por padrão). Os CHECKs
 * de modo e valor entram no SQL de criação porque o SQLite só os aceita aí. As unicidades por dono são índices
 * parciais, válidos nos dois bancos. `establishments (id, company_id)` já é alvo de FK composta (E04).
 *
 * `public_categories` pertence à conta (PD2-12), sem reaproveitar `tags`, e é alvo composto `(id, account_id)` das
 * fichas do diretório (S13).
 */
return new class extends Migration
{
    public function up(): void
    {
        $sections = new Blueprint(DB::connection(), 'profile_sections', function (Blueprint $table): void {
            $table->create();
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('establishment_id')->nullable();
            $table->string('section', 30);
            $table->string('mode', 10);
            $table->json('value')->nullable();
            $table->string('published_mode', 10)->nullable();
            $table->json('published_value')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('published_by')->nullable();
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('clients')->restrictOnDelete();
            $table->foreign(['company_id', 'account_id'])
                ->references(['id', 'account_id'])->on('companies')->restrictOnDelete();
            $table->foreign(['establishment_id', 'company_id'])
                ->references(['id', 'company_id'])->on('establishments')->restrictOnDelete();
            $table->foreign('published_by')->references('id')->on('users')->restrictOnDelete();

            $table->index(['company_id', 'establishment_id']);
        });

        // Modo e valor coerentes nos dois pares. A empresa só tem `override` e o publicado só existe depois do ato.
        $checks = [
            "mode in ('inherit', 'override', 'disabled')",
            "published_mode is null or published_mode in ('inherit', 'override', 'disabled')",
            "(mode = 'override' and value is not null) or (mode <> 'override' and value is null)",
            "(published_mode = 'override' and published_value is not null) "
                ."or (coalesce(published_mode, '') <> 'override' and published_value is null)",
            "establishment_id is not null or mode = 'override'",
            "establishment_id is not null or published_mode is null or published_mode = 'override'",
            'published_mode is not null or published_at is null',
        ];

        $statements = $sections->toSql();
        $statements[0] = substr($statements[0], 0, -1);

        foreach ($checks as $index => $check) {
            $statements[0] .= ', constraint profile_sections_check_'.($index + 1).' check ('.$check.')';
        }

        $statements[0] .= ')';

        foreach ($statements as $statement) {
            DB::statement($statement);
        }

        DB::statement('create unique index profile_sections_company_section_unique on profile_sections '
            .'(company_id, section) where establishment_id is null');
        DB::statement('create unique index profile_sections_establishment_section_unique on profile_sections '
            .'(establishment_id, section) where establishment_id is not null');

        Schema::create('public_categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('name', 80);
            $table->string('normalized_name', 80);
            $table->string('slug', 80);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 160)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('clients')->restrictOnDelete();
            $table->unique(['id', 'account_id']);
            $table->unique(['account_id', 'slug']);
            $table->unique(['account_id', 'normalized_name']);
            $table->index(['account_id', 'sort_order']);
        });

        Schema::table('establishments', function (Blueprint $table): void {
            $table->boolean('show_public_address')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table): void {
            $table->dropColumn('show_public_address');
        });

        Schema::dropIfExists('public_categories');
        Schema::dropIfExists('profile_sections');
    }
};
