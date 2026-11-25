<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Application\Queries\Memberships;

use App\Domains\Plataforma\Domain\Models\Client;

final readonly class ShowAccountMembershipByCodeQuery
{
    /**
     * Conta do código com o vínculo da identidade, numa consulta. Sem vínculo, a conta não é carregada.
     *
     * @return array{0: Client, 1: int, 2: string}|null
     */
    public function execute(int $userId, string $code): ?array
    {
        $account = Client::query()
            ->join('account_memberships as m', 'm.account_id', '=', 'clients.id')
            ->where('clients.code', $code)
            ->where('m.user_id', $userId)
            ->first(['clients.*', 'm.id as context_membership_id', 'm.status as context_membership_status']);

        if ($account === null) {
            return null;
        }

        $membershipId = (int) $account->getAttribute('context_membership_id');
        $status = (string) $account->getAttribute('context_membership_status');

        // A conta segue como modelo limpo: as colunas do vínculo não podem ir para um save posterior.
        $account->offsetUnset('context_membership_id');
        $account->offsetUnset('context_membership_status');
        $account->syncOriginal();

        return [$account, $membershipId, $status];
    }
}
