<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Organizacao\Application\Queries\OrganizationViolationsQuery;
use Illuminate\Console\Command;

final class CheckOrganizationCommand extends Command
{
    protected $signature = 'organization:check';

    protected $description = 'Verifica, sem alterar dados, os invariantes de empresas e estabelecimentos';

    public function handle(OrganizationViolationsQuery $query): int
    {
        $violations = $query->violations();

        foreach ($query->pending() as $pending) {
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
