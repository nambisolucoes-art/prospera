<?php

namespace App\Domains\Pessoas\Domain\Models;

use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use App\Domains\Clientes\Domain\Models\Customer;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Fornecedores\Domain\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** @property string|null $document */
#[Fillable(['name', 'email', 'phone', 'account_id', 'person_type', 'document', 'trade_name', 'state_registration', 'municipal_registration', 'contact_name', 'whatsapp', 'website', 'address', 'additional_phones', 'additional_emails'])]
class Person extends Model implements Auditable
{
    use AuditsChanges;

    protected $attributes = ['person_type' => 'individual', 'additional_phones' => '[]', 'additional_emails' => '[]'];

    protected function casts(): array
    {
        return ['address' => 'array', 'additional_phones' => 'array', 'additional_emails' => 'array'];
    }

    public static function auditType(): string
    {
        return 'person';
    }

    /** @return array<string,string> */
    public static function auditedFields(): array
    {
        return ['name' => 'Nome', 'email' => 'E-mail', 'phone' => 'Telefone', 'additional_phones' => 'Telefones adicionais', 'additional_emails' => 'E-mails adicionais', 'account_id' => 'Conta', 'person_type' => 'Tipo de pessoa', 'document' => 'CPF ou CNPJ', 'trade_name' => 'Nome fantasia', 'state_registration' => 'Inscrição estadual', 'municipal_registration' => 'Inscrição municipal', 'contact_name' => 'Pessoa de contato', 'whatsapp' => 'WhatsApp', 'website' => 'Site', 'address.postal_code' => 'CEP', 'address.street' => 'Logradouro', 'address.number' => 'Número', 'address.complement' => 'Complemento', 'address.neighborhood' => 'Bairro', 'address.city' => 'Cidade', 'address.state' => 'Estado'];
    }

    /** @return HasOne<Customer, $this> */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    /** @return HasOne<Supplier, $this> */
    public function supplier(): HasOne
    {
        return $this->hasOne(Supplier::class);
    }

    /** @return HasOne<User, $this> */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
