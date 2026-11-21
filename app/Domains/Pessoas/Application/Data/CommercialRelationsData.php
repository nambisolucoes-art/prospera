<?php

namespace App\Domains\Pessoas\Application\Data;

use App\Domains\Organizacao\Domain\Models\Company;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Escolhas de relação comercial enviadas pelo formulário de Pessoas, sempre por empresa explícita.
 *
 * Só as empresas e os papéis enviados são considerados; relações ausentes no envio não mudam.
 */
final readonly class CommercialRelationsData
{
    /** @param array<int, array{customer?: bool, supplier?: bool}> $choices */
    public function __construct(public array $choices = []) {}

    public static function fromInput(mixed $input, int $accountId): self
    {
        if ($input === null) {
            return new self;
        }

        $values = Validator::validate(['relations' => $input], [
            'relations' => ['array'],
            'relations.*' => ['array:customer,supplier'],
            'relations.*.customer' => ['sometimes', 'boolean'],
            'relations.*.supplier' => ['sometimes', 'boolean'],
        ], [
            'relations.array' => 'Informe as relações comerciais por empresa.',
            'relations.*.array' => 'Informe Cliente ou Fornecedor para cada empresa.',
            'relations.*.*.boolean' => 'Informe se a relação comercial está marcada.',
        ])['relations'];

        $companyIds = array_keys($values);
        $numeric = array_filter($companyIds, fn ($id): bool => is_int($id) || ctype_digit((string) $id));

        // Empresa de outra conta ou inexistente recebe a mesma resposta.
        $known = $companyIds === [] ? 0 : Company::where('account_id', $accountId)
            ->whereKey($numeric)
            ->count();

        if (count($numeric) !== count($companyIds) || $known !== count($companyIds)) {
            throw ValidationException::withMessages([
                'relations' => 'Selecione empresas desta conta.',
            ]);
        }

        $choices = [];

        foreach ($values as $companyId => $roles) {
            $choice = [];

            foreach (['customer', 'supplier'] as $role) {
                if (array_key_exists($role, $roles)) {
                    $choice[$role] = filter_var($roles[$role], FILTER_VALIDATE_BOOLEAN);
                }
            }

            $choices[(int) $companyId] = $choice;
        }

        return new self($choices);
    }
}
