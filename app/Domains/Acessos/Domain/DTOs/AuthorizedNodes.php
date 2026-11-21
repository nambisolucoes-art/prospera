<?php

namespace App\Domains\Acessos\Domain\DTOs;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final readonly class AuthorizedNodes
{
    /**
     * @param  list<int>  $companies
     * @param  list<int>  $establishments
     */
    public function __construct(
        public bool $tenant = false,
        public array $companies = [],
        public array $establishments = [],
    ) {}

    public function isEmpty(): bool
    {
        return ! $this->tenant && $this->companies === [] && $this->establishments === [];
    }

    // União de descritores, por exemplo das empresas em que alguma ação comercial é permitida.
    public function with(self $other): self
    {
        return new self(
            tenant: $this->tenant || $other->tenant,
            companies: array_values(array_unique([...$this->companies, ...$other->companies])),
            establishments: array_values(array_unique([...$this->establishments, ...$other->establishments])),
        );
    }

    public function covers(AccessNode $node): bool
    {
        return $this->tenant
            || in_array($node->companyId, $this->companies, true)
            || in_array($node->establishmentId, $this->establishments, true);
    }

    /**
     * O chamador já filtra a consulta pela conta ativa.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public function apply(
        Builder $query,
        string $companyColumn = 'company_id',
        ?string $establishmentColumn = 'establishment_id',
    ): void {
        if ($this->tenant) {
            return;
        }

        $query->where(function (Builder $nodes) use ($companyColumn, $establishmentColumn): void {
            $nodes->whereIn($companyColumn, $this->companies);

            if ($establishmentColumn !== null) {
                $nodes->orWhereIn($establishmentColumn, $this->establishments);
            }
        });
    }
}
