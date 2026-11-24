<?php

namespace App\Domains\Acessos\Domain\Models;

use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int $account_id
 * @property int $person_id
 * @property string $status
 */
final class AccountMembership extends Model implements Auditable
{
    use AuditsChanges;

    public static function auditType(): string
    {
        return 'account-membership';
    }

    /** @return array<string,string> */
    public static function auditedFields(): array
    {
        return [
            'user_id' => 'Usuário',
            'account_id' => 'Conta',
            'person_id' => 'Pessoa',
            'status' => 'Acesso à conta',
        ];
    }

    /** @return array<string,string> */
    public static function unauditedFields(): array
    {
        return [
            'last_used_at' => 'Metadado de navegação, sem efeito sobre o acesso.',
            'workspace_establishments' => 'Preferência pessoal de navegação, sem efeito sobre o acesso.',
        ];
    }

    public static function register(User $user, int $accountId, int $personId): self
    {
        $membership = new self;
        $membership->user_id = $user->id;
        $membership->account_id = $accountId;
        $membership->person_id = $personId;
        $membership->status = 'active';

        return $membership;
    }

    /** @return HasMany<RoleAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class, 'membership_id');
    }

    /** @return BelongsTo<User, $this> */
    public function identity(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function isLastActiveAdministrator(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $administrators = RoleAssignment::query()
            ->join('roles', 'roles.id', '=', 'role_assignments.role_id')
            ->join('account_memberships as m', 'm.id', '=', 'role_assignments.membership_id')
            ->join('users', 'users.id', '=', 'm.user_id')
            ->where('role_assignments.account_id', $this->account_id)
            ->where('role_assignments.scope', 'tenant')
            ->where('roles.system_key', 'administrator')
            ->where('m.status', 'active')
            ->where('users.access_enabled', true)
            ->where('users.password_setup_pending', false)
            ->whereNotNull('users.email_verified_at');

        return (clone $administrators)->where('m.id', $this->id)->exists()
            && ! $administrators->where('m.id', '!=', $this->id)->exists();
    }
}
