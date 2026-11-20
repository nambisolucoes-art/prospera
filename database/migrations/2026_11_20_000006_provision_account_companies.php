<?php

use App\Domains\Organizacao\Application\Actions\CreateCompanyAction;
use App\Domains\Organizacao\Application\Data\SaveCompanyData;
use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * E04 (seção 6.5): cada conta recebe a primeira empresa e a unidade principal.
 *
 * Idempotente: só atende contas sem empresa, uma transação por conta. Não se presume que o contratante seja a
 * empresa administrada: a empresa recebe o nome da conta, sem documento, e a conta completa a identificação
 * fiscal (organization:check lista as pendências). A reversão só é aceita antes de uso dos cadastros.
 */
return new class extends Migration
{
    public function up(): void
    {
        $accounts = Client::query()
            ->whereNotIn('id', DB::table('companies')->select('account_id'))
            ->orderBy('id')
            ->get();

        foreach ($accounts as $account) {
            app(CreateCompanyAction::class)->execute(
                $account->id,
                SaveCompanyData::forProvisioning($account, contractorIsCompany: false),
            );
        }
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

        $organizationWasEdited = DB::table('audit_records')
            ->whereIn('entity_type', ['company', 'establishment'])
            ->whereNotNull('company_id')
            ->where('event', '!=', 'created')
            ->exists();

        if ($extraCompanies || $extraEstablishments || $organizationWasEdited) {
            throw new RuntimeException(
                'Há alterações ou cadastros de empresas e estabelecimentos além do provisionamento. '
                .'A reversão perderia dados: restaure o backup ou corrija para frente.',
            );
        }

        DB::table('establishments')->delete();
        DB::table('companies')->delete();
    }
};
