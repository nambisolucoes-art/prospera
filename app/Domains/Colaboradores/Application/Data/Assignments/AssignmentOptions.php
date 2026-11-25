<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Data\Assignments;

/**
 * Escolhas válidas de uma lotação na empresa do vínculo: unidades ativas dentro do escopo de quem gerencia,
 * departamentos e funções ativos (mais os já usados pela lotação em edição, que permanecem).
 */
final readonly class AssignmentOptions
{
    /**
     * @param  array<string,int>  $establishmentIds  código da unidade para o identificador
     * @param  list<array{code: string, name: string}>  $establishments
     * @param  list<array{id: int, name: string}>  $departments
     * @param  list<array{id: int, name: string}>  $jobTitles
     */
    public function __construct(
        public array $establishmentIds,
        public array $establishments,
        public array $departments,
        public array $jobTitles,
    ) {}

    public function hasDepartment(int $id): bool
    {
        return in_array($id, array_column($this->departments, 'id'), true);
    }

    public function hasJobTitle(int $id): bool
    {
        return in_array($id, array_column($this->jobTitles, 'id'), true);
    }

    /** @return array{establishments: list<array{code: string, name: string}>, departments: list<array{id: int, name: string}>, job_titles: list<array{id: int, name: string}>} */
    public function toArray(): array
    {
        return [
            'establishments' => $this->establishments,
            'departments' => $this->departments,
            'job_titles' => $this->jobTitles,
        ];
    }
}
