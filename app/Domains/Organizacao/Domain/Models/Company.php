<?php

namespace App\Domains\Organizacao\Domain\Models;

use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use App\Domains\Organizacao\Domain\DTOs\CompanyDTO;
use App\Support\TaxDocument;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Pessoa jurídica, ou empresário individual, administrada pela conta.
 *
 * @property int $id
 * @property int $account_id
 * @property string $code
 * @property string $name
 * @property string|null $trade_name
 * @property string|null $person_type
 * @property string|null $tax_root
 * @property string|null $individual_document
 * @property bool $active
 */
#[Fillable([
    'account_id',
    'code',
    'name',
    'trade_name',
    'person_type',
    'tax_root',
    'individual_document',
    'tax_regime',
    'main_cnae',
    'active',
])]
class Company extends Model implements Auditable
{
    use AuditsChanges;

    public const TAX_REGIMES = [
        'simples-nacional' => 'Simples Nacional',
        'mei' => 'MEI',
        'lucro-presumido' => 'Lucro presumido',
        'lucro-real' => 'Lucro real',
    ];

    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public static function auditType(): string
    {
        return 'company';
    }

    /** @return array<string,string> */
    public static function auditedFields(): array
    {
        return [
            'account_id' => 'Conta',
            'code' => 'Código',
            'name' => 'Razão social ou nome',
            'trade_name' => 'Nome fantasia',
            'person_type' => 'Tipo de pessoa',
            'tax_root' => 'Raiz do CNPJ',
            'individual_document' => 'CPF',
            'tax_regime' => 'Regime tributário',
            'main_cnae' => 'CNAE principal',
            'active' => 'Situação',
        ];
    }

    // O evento da empresa identifica a própria empresa (DA-12).
    public function auditCompanyId(): ?int
    {
        return $this->getKey() === null ? null : (int) $this->getKey();
    }

    public static function register(CompanyDTO $data, int $accountId, string $code): self
    {
        $company = new self([
            'account_id' => $accountId,
            'code' => $code,
        ]);

        $company->revise($data);

        return $company;
    }

    public function revise(CompanyDTO $data): void
    {
        $this->fill([
            'name' => $data->name,
            'trade_name' => $data->tradeName,
            'person_type' => $data->personType,
            'tax_root' => $data->taxRoot,
            'individual_document' => $data->individualDocument,
            'tax_regime' => $data->taxRegime,
            'main_cnae' => $data->mainCnae,
            'active' => $data->active,
        ]);
    }

    public function taxDocument(): ?TaxDocument
    {
        if ($this->tax_root !== null) {
            return TaxDocument::cnpjRoot($this->tax_root);
        }

        return TaxDocument::cpf($this->individual_document);
    }

    public function admitsEstablishmentTaxDocument(TaxDocument $document): bool
    {
        $company = $this->taxDocument();

        return $company !== null
            && $document->isCnpj()
            && $company->isSameLegalEntityAs($document);
    }

    /**
     * Matriz ou CNPJ de unidade que deixariam de pertencer à pessoa jurídica com o documento atual.
     */
    public function hasEstablishmentsOutsideLegalEntity(): bool
    {
        $establishments = $this->establishments();

        if ($this->tax_root === null) {
            return $establishments
                ->where(fn ($query) => $query
                    ->whereNotNull('tax_document')
                    ->orWhere('is_headquarters', true))
                ->exists();
        }

        return $establishments
            ->whereNotNull('tax_document')
            ->whereNotLike('tax_document', $this->tax_root.'%')
            ->exists();
    }

    /** @return HasMany<Establishment, $this> */
    public function establishments(): HasMany
    {
        return $this->hasMany(Establishment::class);
    }

    /** @return HasOne<Establishment, $this> */
    public function primaryEstablishment(): HasOne
    {
        return $this->hasOne(Establishment::class)->where('is_primary', true);
    }
}
