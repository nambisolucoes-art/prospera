<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Organizacao\Application\Queries\Violations\ListOrganizationViolationsQuery;
use App\Domains\Organizacao\Application\Queries\Violations\ListPendingOrganizationsQuery;
use Illuminate\Console\Command;

final class CheckOrganizationCommand extends Command
{
    protected $signature = 'organization:check';

    protected $description = 'Verifica, sem alterar dados, os invariantes de empresas e estabelecimentos';

    public function handle(
        ListOrganizationViolationsQuery $listOrganizationViolations,
        ListPendingOrganizationsQuery $listPendingOrganizations,
    ): int {
        $violations = $listOrganizationViolations->execute();

        foreach ($listPendingOrganizations->execute() as $pending) {
            $this->warn('Pendência: '.$pending);
        }

        foreach ($violations as $violation) {
            $this->error($violation);
        }

        if ($violations !== []) {
            return self::FAILURE;
        }

        $this->info('Estrutura organizacional validada: nenhuma violação encontrada.');

        return self::SUCCESS;
    }
}
