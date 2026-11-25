<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Queries\Memberships;

use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use Illuminate\Database\Eloquent\Builder;

final readonly class ListVisibleMembershipsQuery
{
    /** @return Builder<AccountMembership> */
    public function execute(int $accountId, AuthorizedNodes $nodes): Builder
    {
        $query = AccountMembership::where('account_memberships.account_id', $accountId);

        if (! $nodes->tenant) {
            $query->whereHas('assignments', fn (Builder $assignments) => $nodes->apply($assignments));
        }

        return $query;
    }
}
