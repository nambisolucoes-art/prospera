<?php

namespace App\Domains\Colaboradores\Application\Data;

use App\Domains\Organizacao\Domain\DTOs\NavigableCompany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Empresas escolhidas no cadastro de departamentos e de funções.
 *
 * Só vale uma empresa que o descritor da permissão cobre. Um código fora dele responde como inexistente, sem
 * revelar empresas que a pessoa não alcança. Com uma única empresa autorizada, a escolha é dispensada.
 */
final readonly class CatalogCompaniesData
{
    /** @param list<NavigableCompany> $companies */
    private function __construct(public array $companies) {}

    /**
     * @param  array<string,mixed>  $input
     * @param  list<NavigableCompany>  $allowed
     * @param  \Closure(): mixed  $siblings  validação dos demais campos, para devolver todos os erros de uma vez
     */
    public static function fromInput(array $input, array $allowed, ?\Closure $siblings = null): self
    {
        if (! array_key_exists('companies', $input) && count($allowed) === 1) {
            return new self($allowed);
        }

        $validator = Validator::make($input, [
            'companies' => ['required', 'array', 'min:1', 'max:200'],
            'companies.*' => ['string', 'max:40'],
        ], [
            'companies.required' => 'Escolha ao menos uma empresa.',
            'companies.array' => 'Escolha ao menos uma empresa.',
            'companies.min' => 'Escolha ao menos uma empresa.',
            'companies.*.string' => 'Escolha empresas válidas.',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();

            try {
                $siblings?->__invoke();
            } catch (ValidationException $other) {
                $errors = [...$other->errors(), ...$errors];
            }

            throw ValidationException::withMessages($errors);
        }

        $values = $validator->validated();

        $selected = [];

        foreach (array_unique($values['companies']) as $code) {
            $company = collect($allowed)->first(fn (NavigableCompany $item) => $item->code === $code);
            abort_if($company === null, 404);
            $selected[] = $company;
        }

        return new self($selected);
    }

    /** @return list<int> */
    public function ids(): array
    {
        return array_map(fn (NavigableCompany $company) => $company->id, $this->companies);
    }
}
