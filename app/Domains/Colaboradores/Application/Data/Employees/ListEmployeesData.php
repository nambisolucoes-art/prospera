<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Data\Employees;

use App\Support\PageSize;

/** Filtros da listagem consolidada de colaboradores. */
final readonly class ListEmployeesData
{
    public function __construct(
        public string $search,
        public ?bool $active,
        public string $company,
        public ?bool $access,
        public int $pageSize,
    ) {}

    /**
     * O filtro de acesso à conta só vale para quem pode listar usuários. Para os demais, é descartado como se
     * não existisse, sem diferença de resposta.
     *
     * @param  array<string,mixed>  $input
     */
    public static function fromInput(array $input, bool $mayFilterAccess): self
    {
        $search = $input['search'] ?? null;
        $company = $input['company'] ?? null;

        return new self(
            search: is_string($search) ? mb_substr(trim($search), 0, 100) : '',
            active: match ($input['status'] ?? null) {
                'active' => true,
                'inactive' => false,
                default => null,
            },
            company: is_string($company) ? mb_substr($company, 0, 40) : '',
            access: $mayFilterAccess
                ? match ($input['access'] ?? null) {
                    'yes' => true,
                    'no' => false,
                    default => null,
                }
            : null,
            pageSize: PageSize::fromInput($input['per_page'] ?? null),
        );
    }

    /** @return array{search: string, status: string, company: string, access: string} */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'status' => match ($this->active) {
                true => 'active',
                false => 'inactive',
                null => '',
            },
            'company' => $this->company,
            'access' => match ($this->access) {
                true => 'yes',
                false => 'no',
                null => '',
            },
        ];
    }
}
