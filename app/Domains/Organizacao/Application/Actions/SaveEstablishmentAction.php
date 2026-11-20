<?php

namespace App\Domains\Organizacao\Application\Actions;

use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Organizacao\Application\Data\SaveEstablishmentData;
use App\Domains\Organizacao\Application\Queries\AvailableOrganizationCodeQuery;
use App\Domains\Organizacao\Domain\DTOs\EstablishmentDTO;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Support\TaxDocument;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class SaveEstablishmentAction
{
    public function __construct(private AvailableOrganizationCodeQuery $codes) {}

    public function execute(Company $company, ?Establishment $establishment, SaveEstablishmentData $data): Establishment
    {
        return AuditLog::transaction(function () use ($company, $establishment, $data): Establishment {
            // Toda alteração de situação ou de principal começa pela trava da empresa (6.6).
            $lockedCompany = Company::where('account_id', $company->account_id)
                ->lockForUpdate()
                ->findOrFail($company->id);

            $lockedEstablishment = $establishment === null
                ? null
                : Establishment::where('company_id', $lockedCompany->id)
                    ->lockForUpdate()
                    ->findOrFail($establishment->id);

            if ($lockedEstablishment === null && ! $lockedCompany->active) {
                throw ValidationException::withMessages([
                    'company_id' => 'A empresa está inativa e não recebe estabelecimentos novos.',
                ]);
            }

            $taxDocument = TaxDocument::cnpj($data->values['tax_document']);

            if ($taxDocument !== null && ! $lockedCompany->admitsEstablishmentTaxDocument($taxDocument)) {
                throw ValidationException::withMessages([
                    'tax_document' => 'O CNPJ deve ter a mesma raiz da empresa.',
                ]);
            }

            $establishmentDTO = new EstablishmentDTO(
                name: $data->values['name'],
                tradeName: $data->values['trade_name'],
                taxDocument: $data->values['tax_document'],
                stateRegistration: $data->values['state_registration'],
                municipalRegistration: $data->values['municipal_registration'],
                isHeadquarters: $data->values['is_headquarters'],
                active: $data->values['active'],
                address: $data->values['address'],
                phone: $data->values['phone'],
                whatsapp: $data->values['whatsapp'],
                email: $data->values['email'],
                additionalPhones: $data->values['additional_phones'],
                additionalEmails: $data->values['additional_emails'],
            );

            if ($lockedEstablishment === null) {
                $code = $data->values['code'] ?? $this->codes->forEstablishment(
                    $lockedCompany->account_id,
                    $lockedCompany->code.' '.$data->values['name'],
                );

                $lockedEstablishment = Establishment::register(
                    $establishmentDTO,
                    $lockedCompany,
                    $code,
                    primary: false,
                );
            } else {
                $lockedEstablishment->revise($establishmentDTO);
            }

            $activationChanged = $lockedEstablishment->exists
                ? $lockedEstablishment->isDirty('active')
                : ! $lockedEstablishment->active;

            // Cada slug fica literal na chamada para que permissions:sync o descubra.
            if ($activationChanged && $lockedEstablishment->active) {
                Gate::authorize('account.establishments.activate', AccessNode::company($lockedCompany->account_id, $lockedCompany->id));
            }

            if ($activationChanged && ! $lockedEstablishment->active) {
                Gate::authorize('account.establishments.deactivate', AccessNode::company($lockedCompany->account_id, $lockedCompany->id));
            }

            if ($activationChanged && $lockedEstablishment->active && ! $lockedCompany->active) {
                throw ValidationException::withMessages([
                    'active' => 'Ative a empresa antes de ativar os estabelecimentos dela.',
                ]);
            }

            // A principal está sempre ativa: a última unidade ativa só sai com a promoção de outra (5.3).
            if (! $lockedEstablishment->active && $lockedEstablishment->is_primary) {
                throw ValidationException::withMessages([
                    'active' => 'Promova outro estabelecimento a principal antes de inativar este.',
                ]);
            }

            if (! $lockedEstablishment->isDirty()) {
                return $lockedEstablishment;
            }

            try {
                $lockedEstablishment->saveOrFail();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'tax_document' => 'Já existe um estabelecimento com este CNPJ ou uma matriz nesta empresa.',
                ]);
            }

            return $lockedEstablishment;
        });
    }
}
