<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Data\Employees;

use App\Domains\Organizacao\Domain\ValueObjects\NavigableCompany;

/** Vínculo de colaborador a criar numa empresa, com os contatos profissionais próprios dela. */
final readonly class NewEmployeeLink
{
    public function __construct(
        public NavigableCompany $company,
        public ?string $workEmail,
        public ?string $workPhone,
        public ?string $workWhatsapp,
    ) {}
}
