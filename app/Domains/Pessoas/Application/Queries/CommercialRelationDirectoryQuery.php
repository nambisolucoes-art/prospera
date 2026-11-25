<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Queries;

use App\Domains\Pessoas\Application\Data\CommercialRelationFiltersData;
use App\Domains\Pessoas\Domain\Enums\CommercialRole;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Support\TaxDocument;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Listagem de Clientes ou Fornecedores: uma linha por relação, já restrita às empresas autorizadas.
 *
 * As empresas chegam do contexto da URL (unidade aberta ou seleção da área de trabalho) intersectadas com a
 * permissão de listar. Filtro, busca, contagem e paginação acontecem no SQL; duas unidades da mesma empresa
 * não repetem a relação, porque a consulta é por empresa.
 */
final class CommercialRelationDirectoryQuery
{
    /**
     * @param  list<int>  $companyIds
     * @return LengthAwarePaginator<int, \stdClass>
     */
    public function list(
        CommercialRole $role,
        int $accountId,
        array $companyIds,
        CommercialRelationFiltersData $filters,
        string $path,
    ): LengthAwarePaginator {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters->search);
        $needle = '%'.$escaped.'%';
        $document = TaxDocument::normalize($filters->search);

        return DB::table($role->table().' as r')
            ->join('people as p', function ($join): void {
                $join->on('p.id', '=', 'r.person_id')->on('p.account_id', '=', 'r.account_id');
            })
            ->join('companies as c', function ($join): void {
                $join->on('c.id', '=', 'r.company_id')->on('c.account_id', '=', 'r.account_id');
            })
            ->where('r.account_id', $accountId)
            ->whereIn('r.company_id', $companyIds)
            ->when($filters->active !== null, fn ($query) => $query->where('r.active', $filters->active))
            ->when($filters->search !== '', fn ($query) => $query->where(fn ($match) => $match
                ->whereLike('p.name', $needle)
                ->orWhereLike('p.email', $needle)
                ->when(strlen($document) >= 11, fn ($fiscal) => $fiscal->orWhere('p.document', $document))))
            ->select([
                'r.id',
                'r.person_id',
                'r.company_id',
                'r.active',
                'p.name',
                'p.person_type',
                'p.document',
                'p.email',
                'c.code as company_code',
                DB::raw('coalesce(c.trade_name, c.name) as company_name'),
                'c.active as company_active',
            ])
            ->orderBy('p.name')
            ->orderBy('r.id')
            ->paginate($filters->pageSize)
            ->withPath($path)
            ->withQueryString();
    }

    public function relation(CommercialRole $role, int $accountId, int $companyId, int $relationId): ?int
    {
        return $this->relationDetails($role, $accountId, $companyId, $relationId)['person_id'] ?? null;
    }

    /** @return array{person_id: int, active: bool}|null */
    public function relationDetails(CommercialRole $role, int $accountId, int $companyId, int $relationId): ?array
    {
        $relation = DB::table($role->table())
            ->where('account_id', $accountId)
            ->where('company_id', $companyId)
            ->where('id', $relationId)
            ->first(['person_id', 'active']);

        return $relation === null ? null : ['person_id' => (int) $relation->person_id, 'active' => (bool) $relation->active];
    }

    /**
     * @param  list<int>  $companyIds
     * @return array<int,bool>
     */
    public function companyStatuses(CommercialRole $role, int $accountId, int $personId, array $companyIds): array
    {
        return DB::table($role->table())
            ->where('account_id', $accountId)
            ->where('person_id', $personId)
            ->whereIn('company_id', $companyIds)
            ->pluck('active', 'company_id')
            ->map(fn ($active): bool => (bool) $active)
            ->all();
    }

    /**
     * Descoberta segura por CPF ou CNPJ completo: só o nome e o tipo, e a relação apenas com esta empresa.
     *
     * @return array{id:int, name:string, person_type:string, relation:string|null}|null
     */
    public function findByDocument(
        CommercialRole $role,
        int $accountId,
        int $companyId,
        string $document,
        string $personType,
    ): ?array {
        $person = DB::table('people')
            ->where('account_id', $accountId)
            ->where('document', $document)
            ->where('person_type', $personType)
            ->first(['id', 'name', 'person_type']);

        if ($person === null) {
            return null;
        }

        $active = DB::table($role->table())
            ->where('account_id', $accountId)
            ->where('company_id', $companyId)
            ->where('person_id', $person->id)
            ->value('active');

        return [
            'id' => (int) $person->id,
            'name' => (string) $person->name,
            'person_type' => (string) $person->person_type,
            'relation' => $active === null ? null : ((bool) $active ? 'active' : 'inactive'),
        ];
    }

    /**
     * @param  array<int, int>  $relationIds
     * @return Collection<int, Collection<int, \stdClass>>
     */
    public function tagsForPage(CommercialRole $role, int $accountId, array $relationIds): Collection
    {
        if ($relationIds === []) {
            return collect();
        }

        return DB::table($role->table().'_tags as selected')
            ->join('tags', 'tags.id', '=', 'selected.tag_id')
            ->where('selected.account_id', $accountId)
            ->whereIntegerInRaw('selected.relation_id', $relationIds)
            ->orderBy('tags.name')
            ->get(['selected.relation_id', 'tags.id', 'tags.name', 'tags.color'])
            ->groupBy('relation_id');
    }

    public function hasActiveRole(CommercialRole $role, int $accountId, int $companyId, int $personId): bool
    {
        return DB::table($role->table())->where('account_id', $accountId)->where('company_id', $companyId)
            ->where('person_id', $personId)->where('active', true)->exists();
    }

    public function centralPerson(int $accountId, int $personId): Person
    {
        return Person::where('account_id', $accountId)->findOrFail($personId);
    }
}
