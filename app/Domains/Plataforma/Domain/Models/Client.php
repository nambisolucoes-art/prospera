<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Domain\Models;

use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use App\Domains\Plataforma\Application\Queries\AccountCodesQuery;
use App\Domains\Plataforma\Domain\DTOs\Accounts\CreateAccountDTO;
use App\Domains\Plataforma\Domain\DTOs\Accounts\UpdateAccountDTO;
use App\Domains\Plataforma\Domain\ValueObjects\AccountCode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string|null $code
 * @property array<string, mixed>|null $details
 */
#[Fillable(['name', 'email', 'person_type', 'document', 'active', 'details', 'plan'])]
class Client extends Model implements Auditable
{
    use AuditsChanges;

    public static function auditType(): string
    {
        return 'account';
    }

    /** @return array<string,string> */
    public static function auditedFields(): array
    {
        return [
            'code' => 'Código da URL',
            'name' => 'Nome',
            'email' => 'E-mail',
            'person_type' => 'Tipo de pessoa',
            'document' => 'Documento',
            'active' => 'Situação',
            'plan' => 'Plano',
            'details.trade_name' => 'Nome fantasia',
            'details.phone' => 'Telefone',
            'details.whatsapp' => 'WhatsApp',
            'details.additional_phones' => 'Telefones adicionais',
            'details.additional_emails' => 'E-mails adicionais',
            'details.address.postal_code' => 'CEP',
            'details.address.street' => 'Logradouro',
            'details.address.number' => 'Número',
            'details.address.complement' => 'Complemento',
            'details.address.neighborhood' => 'Bairro',
            'details.address.city' => 'Cidade',
            'details.address.state' => 'Estado',
        ];
    }

    protected static function booted(): void
    {
        // Código público da URL (E09): sugerido pelo nome fantasia ou pelo nome, só na criação.
        static::creating(function (Client $account): void {
            if (filled($account->code)) {
                return;
            }

            $tradeName = $account->details['trade_name'] ?? null;
            $base = AccountCode::suggestFrom(filled($tradeName) ? (string) $tradeName : (string) $account->name);
            $account->code = AccountCode::firstAvailable($base, app(AccountCodesQuery::class)->startingWith($base));
        });

        static::updating(function (Client $account): void {
            if ($account->isDirty('code')) {
                throw new \LogicException('O código da conta é imutável.');
            }
        });
    }

    public function auditAccountId(): ?int
    {
        return $this->getKey() === null ? null : (int) $this->getKey();
    }

    public static function register(CreateAccountDTO $data): self
    {
        return new self([
            'name' => $data->name,
            'email' => $data->email,
            'plan' => $data->plan,
            'person_type' => $data->personType,
            'document' => $data->document,
            'active' => $data->active,
            'details' => $data->details,
        ]);
    }

    public function revise(UpdateAccountDTO $data): void
    {
        // Os detalhes enviados se somam aos atuais; listas de contatos são substituídas inteiras.
        $details = $this->details;

        if ($data->details !== null) {
            $details = array_replace_recursive($this->details ?? [], $data->details);

            foreach (['additional_phones', 'additional_emails'] as $field) {
                if (isset($data->details[$field])) {
                    $details[$field] = $data->details[$field];
                }
            }
        }

        $this->fill([
            'name' => $data->name,
            'email' => $data->email,
            'plan' => $data->plan,
            'person_type' => $data->personType,
            'document' => $data->document,
            'active' => $data->active,
            'details' => $details,
        ]);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['active' => 'boolean', 'details' => 'array'];
    }

    /** @return HasMany<AccountMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(AccountMembership::class, 'account_id');
    }
}
