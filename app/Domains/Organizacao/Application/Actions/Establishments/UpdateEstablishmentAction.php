<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Actions\Establishments;

use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Organizacao\Application\Data\Establishments\UpdateEstablishmentData;
use App\Domains\Organizacao\Domain\DTOs\Establishments\UpdateEstablishmentDTO;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Support\TaxDocument;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class UpdateEstablishmentAction
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(UpdateEstablishmentData $data, Establishment $establishment): Establishment
    {
        return AuditLog::transaction(function () use ($data, $establishment): Establishment {
            // Ordem única de travas: conta, depois empresa. A auditoria da escrita pede FOR KEY SHARE na conta,
            // e as escritas comerciais travam a conta antes da empresa; a ordem inversa produzia deadlock (E10).
            Client::whereKey($establishment->account_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Toda alteração de situação ou de principal começa pela trava da empresa (6.6).
            $lockedCompany = Company::where('account_id', $establishment->account_id)
                ->lockForUpdate()
                ->findOrFail($establishment->company_id);

            $lockedEstablishment = Establishment::where('company_id', $lockedCompany->id)
                ->lockForUpdate()
                ->findOrFail($establishment->id);

            $taxDocument = TaxDocument::cnpj($data->taxDocument);

            if ($taxDocument !== null && ! $lockedCompany->admitsEstablishmentTaxDocument($taxDocument)) {
                throw ValidationException::withMessages([
                    'tax_document' => 'O CNPJ deve ter a mesma raiz da empresa.',
                ]);
            }

            $establishmentDTO = new UpdateEstablishmentDTO(
                name: $data->name,
                tradeName: $data->tradeName,
                taxDocument: $data->taxDocument,
                stateRegistration: $data->stateRegistration,
                municipalRegistration: $data->municipalRegistration,
                isHeadquarters: $data->isHeadquarters,
                active: $data->active,
                address: $data->address,
                phone: $data->phone,
                whatsapp: $data->whatsapp,
                email: $data->email,
                additionalPhones: $data->additionalPhones,
                additionalEmails: $data->additionalEmails,
            );

            $lockedEstablishment->revise($establishmentDTO);

            $node = AccessNode::company($lockedCompany->account_id, $lockedCompany->id);

            // Cada slug fica literal na chamada para que permissions:sync o descubra.
            if ($lockedEstablishment->isDirty('active') && $lockedEstablishment->active) {
                Gate::authorize('account.establishments.activate', $node);
            }

            if ($lockedEstablishment->isDirty('active') && ! $lockedEstablishment->active) {
                Gate::authorize('account.establishments.deactivate', $node);
            }

            if ($lockedEstablishment->isDirty('active') && $lockedEstablishment->active && ! $lockedCompany->active) {
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
