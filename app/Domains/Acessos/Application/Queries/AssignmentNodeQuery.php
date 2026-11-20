<?php

namespace App\Domains\Acessos\Application\Queries;

use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use Illuminate\Validation\ValidationException;

final class AssignmentNodeQuery
{
    public function resolve(
        int $accountId,
        string $scope,
        ?int $companyId,
        ?int $establishmentId,
        bool $granting,
    ): AccessNode {
        if ($scope === 'tenant' && $companyId === null && $establishmentId === null) {
            return AccessNode::tenant($accountId);
        }

        if (
            $companyId === null
            || ($scope === 'company' && $establishmentId !== null)
            || ($scope === 'establishment' && $establishmentId === null)
            || ! in_array($scope, ['company', 'establishment'], true)
        ) {
            throw ValidationException::withMessages(['grants' => 'Informe o escopo e os dados do local da atribuição.']);
        }

        $company = Company::where('account_id', $accountId)->findOrFail($companyId);
        $unit = $establishmentId === null ? null : Establishment::where('account_id', $accountId)
            ->where('company_id', $company->id)
            ->findOrFail($establishmentId);

        if ($granting && (! $company->active || ($unit !== null && ! $unit->active))) {
            throw ValidationException::withMessages(['grants' => 'Selecione uma empresa e um estabelecimento ativos.']);
        }

        return $unit === null
            ? AccessNode::company($accountId, $company->id)
            : AccessNode::establishment($accountId, $company->id, $unit->id);
    }
}
