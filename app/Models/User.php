<?php

namespace App\Models;

use App\Domains\Acessos\Application\Actions\DefaultRolesAction;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Acessos\Domain\Models\RoleAssignment;
use App\Domains\Auditoria\Application\Actions\AuditContext;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Usuarios\Domain\DTOs\UserIdentityDTO;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property CarbonInterface|null $email_verified_at
 * @property string $password
 * @property bool $is_saas_admin
 * @property bool $is_account_admin
 * @property int|null $account_id
 * @property string|null $profile_photo
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'profile_photo'])]
class User extends Authenticatable implements Auditable, MustVerifyEmail, PasskeyUser
{
    protected $attributes = ['access_enabled' => true];

    private ?AccessNode $initialAccessNode = null;

    use AuditsChanges;

    public static function auditType(): string
    {
        return 'user';
    }

    /** @return array<string,string> */
    public static function auditedFields(): array
    {
        return [
            'access_enabled' => 'Acesso habilitado',
            'name' => 'Nome',
            'email' => 'E-mail',
            'person_id' => 'Pessoa',
            'account_id' => 'Conta',
            'role_id' => 'Cargo',
            'is_saas_admin' => 'Acesso à plataforma',
            'is_account_admin' => 'Administrador inicial',
            'password' => 'Senha',
            'password_setup_pending' => 'Definição inicial de senha pendente',
            'profile_photo' => 'Foto do perfil',
            'email_verified_at' => 'Verificação do e-mail',
            'two_factor_secret' => 'Segundo fator',
            'two_factor_recovery_codes' => 'Códigos de recuperação',
            'two_factor_confirmed_at' => 'Confirmação do segundo fator',
        ];
    }

    /** @return array<string,string> */
    public static function unauditedFields(): array
    {
        return ['remember_token' => 'Token de sessão, nunca armazenado no histórico.'];
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_saas_admin' => 'boolean',
            'is_account_admin' => 'boolean',
            'password' => 'hashed',
            'password_setup_pending' => 'boolean',
            'access_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** @return HasMany<AccountMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(AccountMembership::class);
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return array<int,string> */
    public function permissionSlugs(): array
    {
        return app(EffectivePermissions::class)->slugsFor($this);
    }

    public function applyIdentity(UserIdentityDTO $identity): void
    {
        $this->fill(['name' => $identity->name, 'email' => $identity->email]);

        if ($this->exists && $this->isDirty('email')) {
            $this->email_verified_at = null;
        }
    }

    public function emailChangeRevision(): string
    {
        return hash_hmac('sha256', $this->email.'|'.$this->password, (string) config('app.key'));
    }

    public function isLastAdministratorWithAccess(): bool
    {
        if ($this->account_id === null) {
            return false;
        }

        $membership = AccountMembership::where('account_id', $this->account_id)
            ->where('user_id', $this->id)
            ->first();

        return $membership?->isLastActiveAdministrator() ?? false;
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return BelongsTo<Client, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'account_id');
    }

    public static function profilePhotoPath(int $userId, string $version): string
    {
        return "profile-photos/{$userId}/{$version}.webp";
    }

    public function prepareInitialAssignment(AccessNode $node): void
    {
        $this->initialAccessNode = $node;
    }

    /** @param array<string, mixed> $options */
    public function save(array $options = []): bool
    {
        return (bool) AuditLog::transaction(fn () => $this->saveIdentity($options));
    }

    /** @param array<string,mixed> $options */
    private function saveIdentity(array $options = []): bool
    {
        if (! $this->exists && $this->account_id && ! $this->role_id) {
            [$admin, $member] = app(DefaultRolesAction::class)->execute($this->account_id);
            $this->role_id = $this->is_account_admin ? $admin->id : $member->id;
        }

        if ($this->exists && $this->getAttribute('person_id')) {
            return parent::save($options);
        }

        return $this->getConnection()->transaction(function () use ($options): bool {
            $new = ! $this->exists;
            $roleId = $this->role_id;

            if ($new && $this->initialAccessNode !== null && $this->initialAccessNode->type !== 'tenant') {
                $this->role_id = null;
                $this->is_account_admin = false;
            }

            $person = $this->person()->first() ?? $this->person()->make();
            $person->fill([
                'name' => $this->name,
                'email' => $this->email,
                'account_id' => $this->account_id,
            ]);
            $person->saveOrFail();
            $this->person()->associate($person);

            if (! parent::save($options)) {
                throw new \RuntimeException('Não foi possível salvar o usuário e sua pessoa.');
            }

            if ($new && $this->account_id !== null && ! $this->is_saas_admin) {
                $membership = AccountMembership::register($this, $this->account_id, $person->id);
                $membership->saveOrFail();

                $role = Role::where('account_id', $this->account_id)->find($roleId);

                if ($role !== null) {
                    $node = $this->initialAccessNode ?? AccessNode::tenant($this->account_id);
                    $assignment = RoleAssignment::grant(
                        $membership,
                        $role,
                        $node,
                        app(AuditContext::class)->actorId ?? auth()->user()?->id,
                    );
                    $assignment->saveOrFail();
                }
            }

            if ($this->account_id) {
                Tag::supplierTag($this->account_id);
                Tag::customerTag($this->account_id);
                $tag = Tag::userTag($this->account_id);
                $beforeTags = $person->tags()->pluck('tags.id')->all();
                $person->tags()->syncWithoutDetaching([$tag->id]);

                $afterTags = $person->tags()->pluck('tags.id')->all();
                $person->recordAuditFieldChange('tag_ids', $beforeTags, $afterTags, 'Etiquetas');
            }

            return true;
        });
    }
}
