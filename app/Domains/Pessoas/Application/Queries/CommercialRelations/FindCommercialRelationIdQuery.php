<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Queries\CommercialRelations;

use App\Domains\Pessoas\Domain\Enums\CommercialRole;

/**
 * Listagem de Clientes ou Fornecedores: uma linha por relação, já restrita às empresas autorizadas.
 *
 * As empresas chegam do contexto da URL (unidade aberta ou seleção da área de trabalho) intersectadas com a
 * permissão de listar. Filtro, busca, contagem e paginação acontecem no SQL; duas unidades da mesma empresa
 * não repetem a relação, porque a consulta é por empresa.
 */
final readonly class FindCommercialRelationIdQuery
{
    public function __construct(
        private ShowCommercialRelationQuery $showCommercialRelation,
    ) {}

    public function execute(CommercialRole $role, int $accountId, int $companyId, int $relationId): ?int
    {
        return $this->showCommercialRelation->execute($role, $accountId, $companyId, $relationId)['person_id'] ?? null;
    }
}
