<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Colaboradores\Application\Queries\Violations\ListInstitutionalViolationsQuery;
use Illuminate\Console\Command;

final class CheckInstitutionalCommand extends Command
{
    protected $signature = 'institutional:check';

    protected $description = 'Verifica, sem alterar dados, os invariantes institucionais (lotações de colaboradores)';

    public function handle(ListInstitutionalViolationsQuery $listViolations): int
    {
        $violations = $listViolations->execute();

        foreach ($violations as $violation) {
            $this->error($violation);
        }

        if ($violations !== []) {
            return self::FAILURE;
        }

        $this->info('Estrutura institucional validada: nenhuma violação encontrada.');

        return self::SUCCESS;
    }
}
