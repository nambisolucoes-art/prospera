<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * E09: a identidade global deixa de ter conta própria. A conta vem da URL e é validada pelo vínculo.
 *
 * Reversão limitada: a coluna é recriada e preenchida só para identidades com um único vínculo. Com
 * alguma identidade em várias contas, a reversão aborta antes de alterar o esquema, porque escolher
 * uma conta mudaria o significado dos dados. Nesse caso, a recuperação é pelo backup anterior à implantação.
 */
return new class extends Migration
{
    public function up(): void
    {
        $hasForeignKey = collect(Schema::getForeignKeys('users'))
            ->contains(fn (array $key): bool => $key['columns'] === ['account_id']);

        Schema::table('users', function (Blueprint $table) use ($hasForeignKey): void {
            if ($hasForeignKey) {
                $table->dropForeign(['account_id']);
            }

            $table->dropIndex(['account_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('account_id');
        });
    }

    public function down(): void
    {
        $multipleMemberships = DB::table('account_memberships')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('count(*) > 1');
        $several = DB::query()->fromSub($multipleMemberships, 'multiple_memberships')->count();

        if ($several > 0) {
            throw new RuntimeException(
                "Reversão interrompida: {$several} identidade(s) com vínculos em mais de uma conta. "
                .'Restaure o backup anterior à implantação da E09.',
            );
        }

        // O SQLite da suíte só acrescenta a coluna sem recriar a tabela; no PostgreSQL, a chave volta inteira.
        Schema::table('users', function (Blueprint $table): void {
            if (DB::getDriverName() === 'sqlite') {
                $table->unsignedBigInteger('account_id')->nullable();
            } else {
                $table->foreignId('account_id')->nullable()->constrained('clients')->restrictOnDelete();
            }

            $table->index('account_id');
        });

        DB::table('account_memberships')->orderBy('id')->chunkById(500, function ($memberships): void {
            foreach ($memberships as $membership) {
                DB::table('users')->where('id', $membership->user_id)->update(['account_id' => $membership->account_id]);
            }
        });
    }
};
