<?php

namespace App\Domains\Organizacao\Application\Actions;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Organizacao\Application\Data\SaveCompanyData;
use App\Domains\Organizacao\Application\Queries\AvailableOrganizationCodeQuery;
use App\Domains\Organizacao\Domain\DTOs\CompanyDTO;
use App\Domains\Organizacao\Domain\DTOs\EstablishmentDTO;
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
    public function __construct(private AvailableOrganizationCodeQuery $codes) {}

    public function execute(int $accountId, SaveCompanyData $data): Company
    {
        return AuditLog::transaction(function () use ($accountId, $data): Company {
            // Serializa os cadastros da conta: códigos sugeridos e unicidade fiscal.
            Client::whereKey($accountId)
                ->lockForUpdate()
                ->firstOrFail();

            $companyDTO = new CompanyDTO(
                name: $data->values['name'],
                tradeName: $data->values['trade_name'],
                personType: $data->values['person_type'],
                taxRoot: $data->values['tax_root'],
                individualDocument: $data->values['individual_document'],
                taxRegime: $data->values['tax_regime'],
                mainCnae: $data->values['main_cnae'],
                active: true,
            );

            $companyCode = $data->values['code']
                ?? $this->codes->forCompany($accountId, $data->values['trade_name'] ?? $data->values['name']);

            $company = Company::register($companyDTO, $accountId, $companyCode);

            try {
                $company->saveOrFail();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'document' => 'Já existe uma empresa com este código ou documento nesta conta.',
                ]);
            }

            $primary = $data->primary->values ?? throw new \LogicException('Informe a unidade principal.');

            $establishmentDTO = new EstablishmentDTO(
                name: $primary['name'],
                tradeName: $primary['trade_name'],
                taxDocument: $primary['tax_document'],
                stateRegistration: $primary['state_registration'],
                municipalRegistration: $primary['municipal_registration'],
                isHeadquarters: $primary['is_headquarters'],
                active: true,
                address: $primary['address'],
                phone: $primary['phone'],
                whatsapp: $primary['whatsapp'],
                email: $primary['email'],
                additionalPhones: $primary['additional_phones'],
                additionalEmails: $primary['additional_emails'],
            );

            $primaryCode = $this->codes->forEstablishment(
                $accountId,
                $companyCode.' '.($primary['address']['city'] ?? ''),
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
