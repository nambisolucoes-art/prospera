<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Queries;

use App\Domains\Acessos\Domain\Models\AccountInvitation;
use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;

final class AccountInvitationsQuery
{
    public function forToken(string $token): ?AccountInvitation
    {
        return AccountInvitation::query()
            ->where('token_hash', AccountInvitation::hashToken($token))
            ->with(['account:id,name,active', 'role:id,name'])
            ->first();
    }

    /**
     * Convites pendentes da conta, inclusive vencidos, cujo local está no escopo do operador.
     *
     * @return list<array<string, mixed>>
     */
    public function pending(int $accountId, AuthorizedNodes $nodes, bool $canRevoke): array
    {
        if ($nodes->isEmpty()) {
            return [];
        }

        $rows = AccountInvitation::query()
            ->where('account_id', $accountId)
            ->where('status', 'pending')
            ->when(! $nodes->tenant, fn ($query) => $query->where(fn ($scope) => $scope
                ->whereIn('company_id', $nodes->companies)
                ->orWhereIn('establishment_id', $nodes->establishments)))
            ->with('role:id,name')
            ->orderBy('email')
            ->orderBy('id')
            ->limit(100)
            ->get(['id', 'account_id', 'email', 'role_id', 'scope', 'company_id', 'establishment_id', 'expires_at'])
            ->map(fn (AccountInvitation $invitation): array => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role?->name,
                'expired' => ! $invitation->expires_at->isFuture(),
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'can_revoke' => $canRevoke,
            ])
            ->values()
            ->all();

        return array_values($rows);
    }
}
