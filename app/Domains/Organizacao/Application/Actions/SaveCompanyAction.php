<?php

namespace App\Domains\Organizacao\Application\Actions;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Organizacao\Application\Data\SaveCompanyData;
use App\Domains\Organizacao\Domain\DTOs\CompanyDTO;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SaveCompanyAction
{
    public function execute(Company $company, SaveCompanyData $data): void
    {
        AuditLog::transaction(function () use ($company, $data): void {
            // Ordem única de travas: conta, depois empresa. A auditoria da escrita pede FOR KEY SHARE na conta,
            // e as escritas comerciais travam a conta antes da empresa; a ordem inversa produzia deadlock (E10).
            Client::whereKey($company->account_id)
                ->lockForUpdate()
                ->firstOrFail();

            // A trava da empresa serializa as alterações de situação com as dos estabelecimentos (6.6).
            $lockedCompany = Company::where('account_id', $company->account_id)
                ->lockForUpdate()
                ->findOrFail($company->id);

            $companyDTO = new CompanyDTO(
                name: $data->values['name'],
                tradeName: $data->values['trade_name'],
                personType: $data->values['person_type'],
                taxRoot: $data->values['tax_root'],
                individualDocument: $data->values['individual_document'],
                taxRegime: $data->values['tax_regime'],
                mainCnae: $data->values['main_cnae'],
                active: $data->values['active'],
            );

            $lockedCompany->revise($companyDTO);

            // Cada slug fica literal na chamada para que permissions:sync o descubra.
            if ($lockedCompany->isDirty('active')) {
                if ($lockedCompany->active) {
                    Gate::authorize('account.companies.activate');
                } else {
                    Gate::authorize('account.companies.deactivate');
                }
            }

            $identityChanged = $lockedCompany->isDirty(['person_type', 'tax_root', 'individual_document']);

            if ($identityChanged && $lockedCompany->hasEstablishmentsOutsideLegalEntity()) {
                throw ValidationException::withMessages([
                    'document' => 'Há matriz ou estabelecimentos com CNPJ de outra raiz. '
                        .'Ajuste-os antes de alterar o documento.',
                ]);
            }

            if (! $lockedCompany->isDirty()) {
                return;
            }

            try {
                $lockedCompany->saveOrFail();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'document' => 'Já existe uma empresa com este documento nesta conta.',
                ]);
            }
        });
    }
}
