<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Queries\Memberships;

use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class PageMembershipsQuery
{
    public function __construct(
        private ListVisibleMembershipsQuery $listVisibleMemberships,
    ) {}

    /** @return LengthAwarePaginator<int, AccountMembership> */
    public function execute(int $accountId, AuthorizedNodes $nodes, int $pageSize, string $search): LengthAwarePaginator
    {
        $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        return $this->listVisibleMemberships->execute($accountId, $nodes)
            ->join('people', 'people.id', '=', 'account_memberships.person_id')
            ->join('users', 'users.id', '=', 'account_memberships.user_id')
            ->select('account_memberships.*')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $fields) => $fields
                ->whereLike('people.name', $needle)
                ->orWhereLike('users.email', $needle)))
            ->with([
                'identity:id,email,profile_photo,access_enabled,password_setup_pending',
                'person:id,name',
                'assignments' => fn ($assignments) => $nodes->apply($assignments->getQuery()),
                'assignments.role:id,name',
                'assignments.company:id,name',
                'assignments.establishment:id,name',
            ])
            ->orderBy('people.name')
            ->orderBy('account_memberships.id')
            ->paginate($pageSize)
            ->withQueryString();
    }
}
