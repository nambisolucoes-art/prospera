<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Actions\Companies;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Organizacao\Application\Data\Companies\CreateCompanyData;
use App\Domains\Organizacao\Application\Queries\Codes\AvailableCompanyCodeQuery;
use App\Domains\Organizacao\Application\Queries\Codes\AvailableEstablishmentCodeQuery;
use App\Domains\Organizacao\Domain\DTOs\Companies\CreateCompanyDTO;
use App\Domains\Organizacao\Domain\DTOs\Establishments\CreateEstablishmentDTO;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Cria a empresa e a unidade principal na mesma transação (seção 6.5). Qualquer falha reverte as duas.
 */
final readonly class CreateCompanyAction
{
    public function __construct(
        private AvailableCompanyCodeQuery $availableCompanyCode,
        private AvailableEstablishmentCodeQuery $availableEstablishmentCode,
    ) {}

    /** @throws ValidationException */
    public function execute(CreateCompanyData $data, int $accountId): Company
    {
        return AuditLog::transaction(function () use ($data, $accountId): Company {
            // Serializa os cadastros da conta: códigos sugeridos e unicidade fiscal.
            Client::whereKey($accountId)
                ->lockForUpdate()
                ->firstOrFail();

            $companyDTO = new CreateCompanyDTO(
                name: $data->name,
                tradeName: $data->tradeName,
                personType: $data->personType,
                taxRoot: $data->taxRoot,
                individualDocument: $data->individualDocument,
                taxRegime: $data->taxRegime,
                mainCnae: $data->mainCnae,
                active: true,
            );

            $companyCode = $data->code
                ?? $this->availableCompanyCode->execute($accountId, $data->tradeName ?? $data->name);

            $company = Company::register($companyDTO, $accountId, $companyCode);

            try {
                $company->saveOrFail();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'document' => 'Já existe uma empresa com este código ou documento nesta conta.',
                ]);
            }

            $primary = $data->primary;

            $establishmentDTO = new CreateEstablishmentDTO(
                name: $primary->name,
                tradeName: $primary->tradeName,
                taxDocument: $primary->taxDocument,
                stateRegistration: $primary->stateRegistration,
                municipalRegistration: $primary->municipalRegistration,
                isHeadquarters: $primary->isHeadquarters,
                active: true,
                address: $primary->address,
                phone: $primary->phone,
                whatsapp: $primary->whatsapp,
                email: $primary->email,
                additionalPhones: $primary->additionalPhones,
                additionalEmails: $primary->additionalEmails,
            );

            $primaryCode = $this->availableEstablishmentCode->execute(
                $accountId,
                $companyCode.' '.($primary->address['city'] ?? ''),
            );

            $establishment = Establishment::register($establishmentDTO, $company, $primaryCode, primary: true);

            try {
                $establishment->saveOrFail();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'document' => 'Já existe um estabelecimento com este CNPJ nesta conta.',
                ]);
            }

            return $company;
        });
    }
}
