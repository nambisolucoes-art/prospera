<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// E09: convite de uma identidade existente para outra conta. O vínculo só nasce no aceite.
return new class extends Migration
{
    public function up(): void
    {
        $table = new Blueprint(DB::connection(), 'account_invitations', function (Blueprint $table): void {
            $table->create();
            $table->id();
            $table->foreignId('account_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->restrictOnDelete();
            $table->string('email');
            $table->char('token_hash', 64)->unique();
            $table->unsignedBigInteger('role_id');
            $table->string('scope', 13);
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('establishment_id')->nullable();
            $table->unsignedBigInteger('person_id')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 10)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->foreign(['role_id', 'account_id'])->references(['id', 'account_id'])->on('roles')->restrictOnDelete();
            $table->foreign(['company_id', 'account_id'])->references(['id', 'account_id'])->on('companies')->restrictOnDelete();
            $table->foreign(['establishment_id', 'company_id'])->references(['id', 'company_id'])->on('establishments')->restrictOnDelete();
            $table->foreign(['person_id', 'account_id'])->references(['id', 'account_id'])->on('people')->restrictOnDelete();
            $table->index(['account_id', 'status']);
        });

        $statements = $table->toSql();
        $statements[0] = substr($statements[0], 0, -1).', '
            ."check (status in ('pending', 'accepted', 'revoked')), "
            ."check ((scope = 'tenant' and company_id is null and establishment_id is null) "
            ."or (scope = 'company' and company_id is not null and establishment_id is null) "
            ."or (scope = 'establishment' and company_id is not null and establishment_id is not null)), "
            ."check ((status = 'accepted') = (accepted_at is not null and accepted_by is not null)), "
            .'check (email = lower(email)), '
            .'check (accepted_by is null or accepted_by = recipient_id))';

        foreach ($statements as $statement) {
            DB::statement($statement);
        }

        // Um convite pendente por e-mail em cada conta; o reenvio revoga o anterior na mesma transação.
        DB::statement(
            'create unique index account_invitations_pending on account_invitations (account_id, email) '
            ."where status = 'pending'",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('account_invitations');
    }
};
