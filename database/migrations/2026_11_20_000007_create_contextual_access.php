<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('password_setup_pending')->default(false);
        });

        Schema::table('people', function (Blueprint $table): void {
            $table->unique(['id', 'account_id']);
        });

        DB::statement(
            "alter table roles add column availability varchar(10) not null default 'tenant' "
            ."check (availability in ('tenant', 'restricted'))",
        );

        Schema::table('roles', function (Blueprint $table): void {
            $table->unique(['id', 'account_id']);
        });

        Schema::table('establishments', function (Blueprint $table): void {
            $table->unique(['id', 'company_id']);
        });

        $this->createChecked('account_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained('clients')->restrictOnDelete();
            $table->unsignedBigInteger('person_id');
            $table->string('status', 10)->default('active');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->foreign(['person_id', 'account_id'])->references(['id', 'account_id'])->on('people')->restrictOnDelete();
            $table->unique(['id', 'account_id']);
            $table->unique(['user_id', 'account_id']);
            $table->unique(['account_id', 'person_id']);
            $table->index(['account_id', 'status']);
        }, "status in ('active', 'suspended')");

        Schema::create('role_availability', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('establishment_id')->nullable();
            $this->roleAndNodeKeys($table);
        });

        $this->createChecked('role_assignments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('membership_id');
            $table->unsignedBigInteger('role_id');
            $table->string('scope', 13);
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('establishment_id')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign(['membership_id', 'account_id'])
                ->references(['id', 'account_id'])
                ->on('account_memberships')
                ->restrictOnDelete();
            $this->roleAndNodeKeys($table);
            $table->index(['account_id', 'role_id']);
            $table->index(['account_id', 'company_id']);
            $table->index(['account_id', 'establishment_id']);
        }, <<<'SQL'
            (scope = 'tenant' and company_id is null and establishment_id is null)
            or (scope = 'company' and company_id is not null and establishment_id is null)
            or (scope = 'establishment' and company_id is not null and establishment_id is not null)
            SQL);

        DB::statement(
            'create unique index role_availability_company on role_availability (role_id, company_id) '
            .'where establishment_id is null',
        );
        DB::statement(
            'create unique index role_availability_unit on role_availability (role_id, establishment_id) '
            .'where establishment_id is not null',
        );
        DB::statement(
            "create unique index role_assignments_tenant on role_assignments (membership_id, role_id) where scope = 'tenant'",
        );
        DB::statement(
            'create unique index role_assignments_company on role_assignments (membership_id, role_id, company_id) '
            ."where scope = 'company'",
        );
        DB::statement(
            'create unique index role_assignments_unit on role_assignments (membership_id, role_id, establishment_id) '
            ."where scope = 'establishment'",
        );

        Schema::table('audit_records', function (Blueprint $table): void {
            $table->unsignedBigInteger('membership_id')->nullable();
        });

        // A migração reproduz o cargo anterior. access_enabled continua protegendo a identidade inteira.
        DB::statement(<<<'SQL'
            insert into account_memberships (user_id, account_id, person_id, status, created_at, updated_at)
            select id, account_id, person_id, 'active', current_timestamp, current_timestamp
            from users where account_id is not null and not is_saas_admin
            SQL);

        DB::statement(<<<'SQL'
            insert into role_assignments (account_id, membership_id, role_id, scope, created_at, updated_at)
            select m.account_id, m.id, u.role_id, 'tenant', current_timestamp, current_timestamp
            from account_memberships m join users u on u.id = m.user_id
            where u.role_id is not null
            SQL);
    }

    public function down(): void
    {
        $multipleRoles = DB::table('role_assignments')
            ->select('membership_id')
            ->groupBy('membership_id')
            ->havingRaw('count(*) > 1')
            ->exists();

        $additionalMemberships = DB::table('account_memberships as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where(fn ($query) => $query->whereNull('u.account_id')->orWhereColumn('m.account_id', '!=', 'u.account_id'))
            ->exists();

        if (
            $multipleRoles
            || $additionalMemberships
            || DB::table('role_assignments')->where('scope', '!=', 'tenant')->exists()
            || DB::table('roles')->where('availability', '!=', 'tenant')->exists()
            || DB::table('account_memberships')->where('status', '!=', 'active')->exists()
        ) {
            throw new RuntimeException('A reversão perderia dados contextuais. Preserve os vínculos e as atribuições antes de reverter.');
        }

        DB::statement(<<<'SQL'
            update users set role_id = (
                select a.role_id from role_assignments a
                join account_memberships m on m.id = a.membership_id
                where m.user_id = users.id and m.account_id = users.account_id
            )
            where exists (
                select 1 from account_memberships m where m.user_id = users.id and m.account_id = users.account_id
            )
            SQL);

        Schema::table('audit_records', fn (Blueprint $table) => $table->dropColumn('membership_id'));
        Schema::drop('role_assignments');
        Schema::drop('role_availability');
        Schema::drop('account_memberships');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('password_setup_pending'));
        Schema::table('establishments', fn (Blueprint $table) => $table->dropUnique(['id', 'company_id']));
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropUnique(['id', 'account_id']);
            $table->dropColumn('availability');
        });
        Schema::table('people', fn (Blueprint $table) => $table->dropUnique(['id', 'account_id']));
    }

    private function roleAndNodeKeys(Blueprint $table): void
    {
        $table->foreign(['role_id', 'account_id'])->references(['id', 'account_id'])->on('roles')->restrictOnDelete();
        $table->foreign(['company_id', 'account_id'])->references(['id', 'account_id'])->on('companies')->restrictOnDelete();
        $table->foreign(['establishment_id', 'company_id'])
            ->references(['id', 'company_id'])
            ->on('establishments')
            ->restrictOnDelete();
    }

    private function createChecked(string $name, Closure $columns, string $check): void
    {
        $table = new Blueprint(DB::connection(), $name, function (Blueprint $table) use ($columns): void {
            $table->create();
            $columns($table);
        });

        $statements = $table->toSql();
        $statements[0] = substr($statements[0], 0, -1).", check ($check))";

        foreach ($statements as $statement) {
            DB::statement($statement);
        }
    }
};
