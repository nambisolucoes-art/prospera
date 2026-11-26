<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Queries\Profile;

use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;
use Illuminate\Support\Facades\DB;

/** Dados de identificação do dono do perfil para a tela, lidos dentro da conta. */
final readonly class ShowProfileOwnerQuery
{
    /**
     * @return array{type: string, code: string, name: string, active: bool, company_code: string, company_name: string, show_public_address: bool, has_address: bool}|null
     */
    public function execute(ProfileOwner $owner): ?array
    {
        $company = DB::table('companies')
            ->where('account_id', $owner->accountId)
            ->where('id', $owner->companyId)
            ->first(['code', 'name', 'trade_name', 'active']);

        if ($company === null) {
            return null;
        }

        $companyName = (string) ($company->trade_name ?: $company->name);

        if (! $owner->isEstablishment()) {
            return [
                'type' => 'company',
                'code' => (string) $company->code,
                'name' => $companyName,
                'active' => (bool) $company->active,
                'company_code' => (string) $company->code,
                'company_name' => $companyName,
                'show_public_address' => false,
                'has_address' => false,
            ];
        }

        $unit = DB::table('establishments')
            ->where('account_id', $owner->accountId)
            ->where('company_id', $owner->companyId)
            ->where('id', $owner->establishmentId)
            ->first(['code', 'name', 'active', 'show_public_address', 'address']);

        if ($unit === null) {
            return null;
        }

        $address = $unit->address === null ? [] : (json_decode((string) $unit->address, true) ?: []);

        return [
            'type' => 'establishment',
            'code' => (string) $unit->code,
            'name' => (string) $unit->name,
            'active' => (bool) $unit->active && (bool) $company->active,
            'company_code' => (string) $company->code,
            'company_name' => $companyName,
            'show_public_address' => (bool) $unit->show_public_address,
            'has_address' => ! empty($address['city']) && ! empty($address['state']),
        ];
    }
}
