<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Queries\Employees;

use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use App\Domains\Colaboradores\Application\Data\Employees\ListEmployeesData;
use App\Domains\Colaboradores\Domain\Models\Employee;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Support\TaxDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Lista uma linha por Pessoa com ao menos um vínculo de colaborador dentro das empresas que o descritor autoriza.
 *
 * O escopo, os filtros, a busca, a contagem e a paginação acontecem no SQL, sobre a tabela de pessoas, e por isso
 * a pessoa nunca se repete. Vínculos fora do descritor não participam de nenhum filtro nem da contagem.
 */
final readonly class ListEmployeesQuery
{
    /** @return LengthAwarePaginator<int, Person> */
    public function execute(
        int $accountId,
        AuthorizedNodes $nodes,
        ListEmployeesData $filters,
        bool $canViewPeople,
        bool $canSeeAccess,
        string $path,
    ): LengthAwarePaginator {
        $links = Employee::query()
            ->select('employees.person_id')
            ->where('employees.account_id', $accountId)
            ->when($filters->active !== null, fn (Builder $query) => $query->where('employees.active', $filters->active))
            ->when($filters->company !== '', fn (Builder $query) => $query->whereIn(
                'employees.company_id',
                DB::table('companies')
                    ->where('account_id', $accountId)
                    ->where('code', $filters->company)
                    ->select('id'),
            ));

        $nodes->apply($links, 'employees.company_id', null);

        $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters->search).'%';
        $document = TaxDocument::normalize($filters->search);

        $memberships = DB::table('account_memberships')
            ->where('account_id', $accountId)
            ->where('status', 'active')
            ->select('person_id');

        $query = Person::query()
            ->where('people.account_id', $accountId)
            ->whereIn('people.id', $links)
            ->select(['people.id', 'people.name', 'people.person_type'])
            ->when($canViewPeople, fn (Builder $query) => $query->addSelect(['people.document', 'people.email']))
            ->when($canSeeAccess, fn (Builder $query) => $query->selectRaw(
                'exists (select 1 from account_memberships m where m.person_id = people.id '
                .'and m.account_id = people.account_id and m.status = ?) as has_access',
                ['active'],
            ))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(
                fn (Builder $match) => $match
                    ->whereLike('people.name', $needle)
                    ->when($canViewPeople, fn (Builder $fiscal) => $fiscal->orWhereLike('people.email', $needle))
                    ->when(
                        $canViewPeople && strlen($document) >= 11,
                        fn (Builder $fiscal) => $fiscal->orWhere('people.document', $document),
                    ),
            ))
            ->when($filters->access === true, fn (Builder $query) => $query->whereIn('people.id', $memberships))
            ->when($filters->access === false, fn (Builder $query) => $query->whereNotIn('people.id', $memberships));

        return $query
            ->orderBy('people.name')
            ->orderBy('people.id')
            ->paginate($filters->pageSize)
            ->withPath($path)
            ->withQueryString();
    }
}
