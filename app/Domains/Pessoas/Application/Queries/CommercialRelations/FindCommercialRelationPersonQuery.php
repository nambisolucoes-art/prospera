<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Queries\CommercialRelations;

use App\Domains\Pessoas\Domain\Enums\CommercialRole;
use Illuminate\Support\Facades\DB;

/**
 * Listagem de Clientes ou Fornecedores: uma linha por relação, já restrita às empresas autorizadas.
 *
 * As empresas chegam do contexto da URL (unidade aberta ou seleção da área de trabalho) intersectadas com a
 * permissão de listar. Filtro, busca, contagem e paginação acontecem no SQL; duas unidades da mesma empresa
 * não repetem a relação, porque a consulta é por empresa.
 */
final readonly class FindCommercialRelationPersonQuery
{
    /**
     * Descoberta segura por CPF ou CNPJ completo: só o nome e o tipo, e a relação apenas com esta empresa.
     *
     * @return array{id:int, name:string, person_type:string, relation:string|null}|null
     */
    public function execute(
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
}
