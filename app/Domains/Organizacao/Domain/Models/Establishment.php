<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Domain\Models;

use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use App\Domains\Organizacao\Domain\DTOs\EstablishmentDTO;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unidade operacional de uma empresa, com ou sem CNPJ próprio.
 *
 * @property int $id
 * @property int $account_id
 * @property int $company_id
 * @property string $code
 * @property string $name
 * @property string|null $trade_name
 * @property string|null $tax_document
 * @property array<string,string|null>|null $address
 * @property bool $is_headquarters
 * @property bool $is_primary
 * @property bool $active
 */
#[Fillable([
    'account_id',
    'company_id',
    'code',
    'name',
    'trade_name',
    'tax_document',
    'state_registration',
    'municipal_registration',
    'is_headquarters',
    'is_primary',
    'active',
    'address',
    'phone',
    'whatsapp',
    'email',
    'additional_phones',
    'additional_emails',
])]
class Establishment extends Model implements Auditable
{
    use AuditsChanges;

    protected $attributes = [
        'is_headquarters' => false,
        'is_primary' => false,
        'active' => true,
        'additional_phones' => '[]',
        'additional_emails' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'is_headquarters' => 'boolean',
            'is_primary' => 'boolean',
            'active' => 'boolean',
            'address' => 'array',
            'additional_phones' => 'array',
            'additional_emails' => 'array',
        ];
    }

    public static function auditType(): string
    {
        return 'establishment';
    }

    /** @return array<string,string> */
    public static function auditedFields(): array
    {
        return [
            'account_id' => 'Conta',
            'company_id' => 'Empresa',
            'code' => 'Código',
            'name' => 'Nome',
            'trade_name' => 'Nome fantasia',
            'tax_document' => 'CNPJ',
            'state_registration' => 'Inscrição estadual',
            'municipal_registration' => 'Inscrição municipal',
            'is_headquarters' => 'Matriz',
            'is_primary' => 'Principal',
            'active' => 'Situação',
            'address.postal_code' => 'CEP',
            'address.street' => 'Logradouro',
            'address.number' => 'Número',
            'address.complement' => 'Complemento',
            'address.neighborhood' => 'Bairro',
            'address.city' => 'Cidade',
            'address.state' => 'Estado',
            'phone' => 'Telefone',
            'whatsapp' => 'WhatsApp',
            'email' => 'E-mail',
            'additional_phones' => 'Telefones adicionais',
            'additional_emails' => 'E-mails adicionais',
        ];
    }

    // O evento da unidade identifica a própria unidade e a empresa dela (DA-12).
    public function auditEstablishmentId(): ?int
    {
        return $this->getKey() === null ? null : (int) $this->getKey();
    }

    public static function register(EstablishmentDTO $data, Company $company, string $code, bool $primary): self
    {
        $establishment = new self([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'code' => $code,
            'is_primary' => $primary,
        ]);

        $establishment->revise($data);

        return $establishment;
    }

    public function revise(EstablishmentDTO $data): void
    {
        $this->fill([
            'name' => $data->name,
            'trade_name' => $data->tradeName,
            'tax_document' => $data->taxDocument,
            'state_registration' => $data->stateRegistration,
            'municipal_registration' => $data->municipalRegistration,
            'is_headquarters' => $data->isHeadquarters,
            'active' => $data->active,
            'address' => $data->address,
            'phone' => $data->phone,
            'whatsapp' => $data->whatsapp,
            'email' => $data->email,
            'additional_phones' => $data->additionalPhones,
            'additional_emails' => $data->additionalEmails,
        ]);
    }

    public function markAsPrimary(): void
    {
        $this->is_primary = true;
    }

    public function unmarkAsPrimary(): void
    {
        $this->is_primary = false;
    }

    // Classificação jurídica explícita (DA-04): sem CNPJ próprio, a unidade não é matriz nem filial.
    public function legalClassification(): string
    {
        if ($this->is_headquarters) {
            return 'headquarters';
        }

        return $this->tax_document === null ? 'unregistered' : 'branch';
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
