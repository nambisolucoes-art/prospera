<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Actions\Establishments;

use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Organizacao\Application\Data\Establishments\CreateEstablishmentData;
use App\Domains\Organizacao\Application\Queries\Codes\AvailableEstablishmentCodeQuery;
use App\Domains\Organizacao\Domain\DTOs\Establishments\CreateEstablishmentDTO;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Support\TaxDocument;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class CreateEstablishmentAction
{
    public function __construct(private AvailableEstablishmentCodeQuery $availableEstablishmentCode) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(CreateEstablishmentData $data, Company $company): Establishment
    {
        return AuditLog::transaction(function () use ($data, $company): Establishment {
            // Ordem única de travas: conta, depois empresa. A auditoria da escrita pede FOR KEY SHARE na conta,
            // e as escritas comerciais travam a conta antes da empresa; a ordem inversa produzia deadlock (E10).
            Client::whereKey($company->account_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Toda alteração de situação ou de principal começa pela trava da empresa (6.6).
            $lockedCompany = Company::where('account_id', $company->account_id)
                ->lockForUpdate()
                ->findOrFail($company->id);

            if (! $lockedCompany->active) {
                throw ValidationException::withMessages([
                    'company_id' => 'A empresa está inativa e não recebe estabelecimentos novos.',
                ]);
            }

            $taxDocument = TaxDocument::cnpj($data->taxDocument);

            if ($taxDocument !== null && ! $lockedCompany->admitsEstablishmentTaxDocument($taxDocument)) {
                throw ValidationException::withMessages([
                    'tax_document' => 'O CNPJ deve ter a mesma raiz da empresa.',
                ]);
            }

            $establishmentDTO = new CreateEstablishmentDTO(
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

            $code = $data->code ?? $this->availableEstablishmentCode->execute(
                $lockedCompany->account_id,
                $lockedCompany->code.' '.$data->name,
            );

            $establishment = Establishment::register($establishmentDTO, $lockedCompany, $code, primary: false);

            $node = AccessNode::company($lockedCompany->account_id, $lockedCompany->id);

            // Cada slug fica literal na chamada para que permissions:sync o descubra.
            if (! $establishment->active) {
                Gate::authorize('account.establishments.deactivate', $node);
            }

            // A principal está sempre ativa e nasce de outra Action.
            try {
                $establishment->saveOrFail();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'tax_document' => 'Já existe um estabelecimento com este CNPJ ou uma matriz nesta empresa.',
                ]);
            }

            return $establishment;
        });
    }
}
