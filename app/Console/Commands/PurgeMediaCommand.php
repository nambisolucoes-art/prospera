<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Midia\Application\Actions\PurgeMediaResiduesAction;
use Illuminate\Console\Command;

final class PurgeMediaCommand extends Command
{
    protected $signature = 'media:purge {--dry-run : Só mostra o que seria removido} {--grace= : Minutos de tolerância para resíduos recentes}';

    protected $description = 'Remove resíduos da biblioteca de mídia (arquivos sem linha e exclusões solicitadas sem uso)';

    public function handle(PurgeMediaResiduesAction $purge): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $grace = $this->option('grace') !== null ? (int) $this->option('grace') : (int) config('media.orphan_grace_minutes');

        $report = $purge->execute($dryRun, $grace);

        $this->line(($dryRun ? '[simulação] ' : '').'Originais órfãos: '.$report['orphan_originals']);
        $this->line(($dryRun ? '[simulação] ' : '').'Derivados órfãos: '.$report['orphan_variants']);
        $this->line(($dryRun ? '[simulação] ' : '').'Mídias com exclusão solicitada e sem uso: '.$report['released']);

        foreach ($report['missing_files'] as $id) {
            $this->warn("Mídia {$id} tem arquivo ausente no armazenamento (não é corrigida por este comando).");
        }

        foreach ($report['without_variants'] as $id) {
            $this->warn("Mídia {$id} está sem variantes (não é corrigida por este comando).");
        }

        return self::SUCCESS;
    }
}
