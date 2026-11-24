<?php

namespace App\Domains\Organizacao\Application\Actions;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Validation\ValidationException;

/**
 * Troca atômica da unidade principal (seção 6.6). A trava da empresa serializa promoções e inativações
 * concorrentes; a antiga é desmarcada antes da nova porque o índice parcial é verificado por instrução.
 */
final class PromotePrimaryEstablishmentAction
{
    public function execute(Establishment $establishment): void
    {
        AuditLog::transaction(function () use ($establishment): void {
            // Ordem única de travas: conta, depois empresa. A auditoria da escrita pede FOR KEY SHARE na conta,
            // e as escritas comerciais travam a conta antes da empresa; a ordem inversa produzia deadlock (E10).
            Client::whereKey($establishment->account_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedCompany = Company::where('account_id', $establishment->account_id)
                ->lockForUpdate()
                ->findOrFail($establishment->company_id);

            $lockedTarget = Establishment::where('company_id', $lockedCompany->id)
                ->lockForUpdate()
                ->findOrFail($establishment->id);

            if ($lockedTarget->is_primary) {
                return;
            }

            if (! $lockedCompany->active) {
                throw ValidationException::withMessages([
                    'establishment' => 'A empresa está inativa. Ative-a antes de trocar a unidade principal.',
                ]);
            }

            if (! $lockedTarget->active) {
                throw ValidationException::withMessages([
                    'establishment' => 'Ative o estabelecimento antes de torná-lo principal.',
                ]);
            }

            $lockedCurrent = Establishment::where('company_id', $lockedCompany->id)
                ->where('is_primary', true)
                ->lockForUpdate()
                ->first();

            if ($lockedCurrent !== null) {
                $lockedCurrent->unmarkAsPrimary();
                $lockedCurrent->saveOrFail();
            }

            $lockedTarget->markAsPrimary();
            $lockedTarget->saveOrFail();
        });
    }
}
