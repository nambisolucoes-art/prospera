<?php

declare(strict_types=1);

namespace App\Domains\Auditoria\Application\Queries;

use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AuditFilterOptionsQuery
{
    /**
     * Autores, tipos e ações saem da mesma consulta com escopo da listagem; empresas e unidades, dos nós
     * autorizados. Nenhuma opção revela evento, pessoa ou unidade fora do conjunto autorizado.
     *
     * @param  Builder<AuditRecord>  $scope
     * @return array<string,mixed>
     */
    public function options(Builder $scope, ?int $accountId, ?AuthorizedNodes $nodes): array
    {
        // No escopo da conta inteira, a varredura solta percorre só os valores distintos do índice.
        if ($accountId !== null && $nodes?->tenant === true) {
            $actors = $this->tenantActors($accountId);
            $pairs = $this->tenantEntityEvents($accountId);
        } else {
            $actors = (clone $scope)
                ->whereNotNull('actor_id')
                ->select('actor_id', 'actor_name')
                ->distinct()
                ->orderBy('actor_name')
                ->get()
                ->map(fn ($record) => ['value' => (string) $record->actor_id, 'label' => $record->actor_name])
                ->unique('value')
                ->values();

            $pairs = (clone $scope)
                ->select('entity_type', 'event')
                ->distinct()
                ->get()
                ->map(fn ($record) => ['type' => $record->entity_type, 'event' => $record->event]);
        }

        $entities = $pairs
            ->pluck('type')
            ->unique()
            ->map(fn ($type) => ['value' => $type, 'label' => config('audit.entities.'.$type, $type)])
            ->sortBy('label')
            ->values();

        $events = $pairs
            ->pluck('event')
            ->unique()
            ->map(fn ($event) => ['value' => $event, 'label' => config('audit.events.'.$event, $event)])
            ->sortBy('label')
            ->values();

        /** @var array<string,string> $originLabels */
        $originLabels = config('audit.origins');
        $origins = collect($originLabels)
            ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
            ->values();

        if ($accountId === null || $nodes === null) {
            $accounts = Client::orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($account) => ['value' => (string) $account->id, 'label' => $account->name]);

            return [
                'actors' => $actors,
                'entities' => $entities,
                'events' => $events,
                'origins' => $origins,
                'accounts' => $accounts,
                'companies' => [],
                'establishments' => [],
                'levels' => [],
            ];
        }

        $units = $this->units($accountId, $nodes);

        $companies = $units
            ->unique('company_id')
            ->map(fn ($unit) => ['value' => (string) $unit->company_id, 'label' => (string) $unit->getAttribute('company_name')])
            ->sortBy('label')
            ->values();

        $establishments = $units
            ->map(fn ($unit) => [
                'value' => (string) $unit->id,
                'label' => $unit->name,
                'company' => (string) $unit->company_id,
            ])
            ->sortBy('label')
            ->values();

        // Os níveis oferecidos são os que a autorização alcança: eventos da conta só com escopo de conta.
        $levels = [];

        if ($nodes->tenant) {
            $levels[] = 'tenant';
        }

        if ($nodes->tenant || $nodes->companies !== []) {
            $levels[] = 'company';
        }

        $levels[] = 'establishment';

        return [
            'actors' => $actors,
            'entities' => $entities,
            'events' => $events,
            'origins' => $origins,
            'accounts' => [],
            'companies' => $companies,
            'establishments' => $establishments,
            'levels' => array_map(
                fn ($level) => ['value' => $level, 'label' => config('audit.levels.'.$level)],
                $levels,
            ),
        ];
    }

    /**
     * Autores distintos da conta pelo índice (account_id, actor_id, created_at), com o nome mais recente gravado.
     *
     * @return Collection<int,array{value:string,label:string}>
     */
    private function tenantActors(int $accountId): Collection
    {
        $rows = DB::select(
            <<<'SQL'
            with recursive actors (id) as (
                select min(actor_id) from audit_records where account_id = ?
                union all
                select (select min(actor_id) from audit_records where account_id = ? and actor_id > actors.id)
                from actors
                where actors.id is not null
            )
            select actors.id, (
                select actor_name from audit_records
                where account_id = ? and actor_id = actors.id
                order by created_at desc
                limit 1
            ) as name
            from actors
            where actors.id is not null
            SQL,
            [$accountId, $accountId, $accountId],
        );

        return collect($rows)
            ->map(fn ($row) => ['value' => (string) $row->id, 'label' => (string) $row->name])
            ->sortBy('label')
            ->values();
    }

    /**
     * Pares distintos de tipo e ação da conta pelo índice (account_id, entity_type, event).
     *
     * @return Collection<int,array{type:string,event:string}>
     */
    private function tenantEntityEvents(int $accountId): Collection
    {
        $rows = DB::select(
            <<<'SQL'
            with recursive types (type) as (
                select min(entity_type) from audit_records where account_id = ?
                union all
                select (select min(entity_type) from audit_records where account_id = ? and entity_type > types.type)
                from types
                where types.type is not null
            ),
            pairs (type, event) as (
                select types.type, (
                    select min(event) from audit_records where account_id = ? and entity_type = types.type
                )
                from types
                where types.type is not null
                union all
                select pairs.type, (
                    select min(event) from audit_records
                    where account_id = ? and entity_type = pairs.type and event > pairs.event
                )
                from pairs
                where pairs.event is not null
            )
            select type, event from pairs where event is not null
            SQL,
            [$accountId, $accountId, $accountId, $accountId],
        );

        return collect($rows)->map(fn ($row) => ['type' => (string) $row->type, 'event' => (string) $row->event]);
    }

    /** @return Collection<int,Establishment> */
    private function units(int $accountId, AuthorizedNodes $nodes): Collection
    {
        $query = Establishment::query()
            ->join('companies', function ($join) {
                $join->on('companies.id', '=', 'establishments.company_id')
                    ->on('companies.account_id', '=', 'establishments.account_id');
            })
            ->where('establishments.account_id', $accountId)
            ->select([
                'establishments.id',
                'establishments.name',
                'establishments.company_id',
                'companies.name as company_name',
            ]);

        $nodes->apply($query, 'establishments.company_id', 'establishments.id');

        return $query->get();
    }
}
