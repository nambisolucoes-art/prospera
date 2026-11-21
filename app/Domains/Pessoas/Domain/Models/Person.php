<?php

namespace App\Domains\Pessoas\Domain\Models;

use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use App\Domains\Clientes\Domain\Models\Customer;
use App\Domains\Etiquetas\Domain\Concerns\NamesAuditedTags;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Fornecedores\Domain\Models\Supplier;
use App\Domains\Pessoas\Domain\DTOs\PersonDTO;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Validation\ValidationException;

/** @property string|null $document */
#[Fillable([
    'name',
    'email',
    'phone',
    'account_id',
    'person_type',
    'document',
    'trade_name',
    'state_registration',
    'municipal_registration',
    'contact_name',
    'whatsapp',
    'website',
    'address',
    'additional_phones',
    'additional_emails',
])]
class Person extends Model implements Auditable
{
    use AuditsChanges;
    use NamesAuditedTags {
        NamesAuditedTags::auditRelationNames insteadof AuditsChanges;
    }

    /**
     * Identificação central: alterá-la exige cobrir todas as empresas relacionadas (seção 10.2.1).
     */
    public const IDENTIFICATION_FIELDS = [
        'name',
        'person_type',
        'document',
        'trade_name',
        'state_registration',
        'municipal_registration',
    ];

    /**
     * Contatos e endereço centrais: alterá-los exige cobrir pelo menos uma empresa relacionada.
     */
    public const CONTACT_FIELDS = [
        'email',
        'phone',
        'whatsapp',
        'additional_phones',
        'additional_emails',
        'website',
        'contact_name',
        'address',
    ];

    protected $attributes = [
        'person_type' => 'individual',
        'additional_phones' => '[]',
        'additional_emails' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'address' => 'array',
            'additional_phones' => 'array',
            'additional_emails' => 'array',
        ];
    }

    public static function auditType(): string
    {
        return 'person';
    }

    /** @return array<string,string> */
    public static function auditedFields(): array
    {
        return [
            'name' => 'Nome',
            'email' => 'E-mail',
            'phone' => 'Telefone',
            'additional_phones' => 'Telefones adicionais',
            'additional_emails' => 'E-mails adicionais',
            'account_id' => 'Conta',
            'person_type' => 'Tipo de pessoa',
            'document' => 'CPF ou CNPJ',
            'trade_name' => 'Nome fantasia',
            'state_registration' => 'Inscrição estadual',
            'municipal_registration' => 'Inscrição municipal',
            'contact_name' => 'Pessoa de contato',
            'whatsapp' => 'WhatsApp',
            'website' => 'Site',
            'address.postal_code' => 'CEP',
            'address.street' => 'Logradouro',
            'address.number' => 'Número',
            'address.complement' => 'Complemento',
            'address.neighborhood' => 'Bairro',
            'address.city' => 'Cidade',
            'address.state' => 'Estado',
        ];
    }

    public static function register(PersonDTO $data, int $accountId): self
    {
        $person = new self(['account_id' => $accountId]);
        $person->revise($data);

        return $person;
    }

    public function revise(PersonDTO $data): void
    {
        $this->fill([
            'name' => $data->name,
            'email' => $data->email,
            'phone' => $data->phone,
            'person_type' => $data->personType,
            'document' => $data->document,
            'trade_name' => $data->tradeName,
            'state_registration' => $data->stateRegistration,
            'municipal_registration' => $data->municipalRegistration,
            'contact_name' => $data->contactName,
            'whatsapp' => $data->whatsapp,
            'website' => $data->website,
            'address' => $data->address,
            'additional_phones' => $data->additionalPhones,
            'additional_emails' => $data->additionalEmails,
        ]);
    }

    /** @param array<int,int> $tagIds */
    public function assignTags(array $tagIds): void
    {
        // Trava as etiquetas para que uma exclusão não concorra com o vínculo.
        $tags = Tag::where('account_id', $this->account_id)
            ->whereIn('id', $tagIds)
            ->lockForUpdate()
            ->get();

        $before = $this->tags()->pluck('tags.id')->all();

        // Etiqueta inativa só permanece quando a pessoa já a possuía.
        if (
            $tags->count() !== count($tagIds)
            || $tags->contains(fn (Tag $tag) => ! $tag->active && ! in_array($tag->id, $before, true))
        ) {
            throw ValidationException::withMessages([
                'tag_ids' => 'Selecione etiquetas ativas desta conta.',
            ]);
        }

        $this->tags()->sync($tagIds);
        $this->recordAuditFieldChange('tag_ids', $before, $tagIds, 'Etiquetas');
    }

    /** @return HasMany<Customer, $this> */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /** @return HasMany<Supplier, $this> */
    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    /** @return HasOneThrough<User, AccountMembership, $this> */
    public function user(): HasOneThrough
    {
        return $this->hasOneThrough(User::class, AccountMembership::class, 'person_id', 'id', 'id', 'user_id');
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
