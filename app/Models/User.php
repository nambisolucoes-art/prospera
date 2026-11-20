<?php

namespace App\Models;

use App\Domains\Acessos\Application\Actions\DefaultRolesAction;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Usuarios\Domain\DTOs\UserIdentityDTO;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property Carbon|null $email_verified_at
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
            'access_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return array<int,string> */
    public function permissionSlugs(): array
    {
        if (! $this->access_enabled) {
            return [];
        }

        $systemPermissions = Permission::where('is_system', true);

        if ($this->is_saas_admin) {
            return $systemPermissions
                ->whereNull('account_id')
                ->whereIn('scope', ['platform', 'self'])
                ->pluck('slug')
                ->map(fn ($slug): string => (string) $slug)
                ->all();
        }

        if (! $this->account_id) {
            return $systemPermissions
                ->whereNull('account_id')
                ->where('scope', 'self')
                ->pluck('slug')
                ->map(fn ($slug): string => (string) $slug)
                ->all();
        }

        if (! $this->account()->where('active', true)->exists()) {
            return [];
        }

        return $systemPermissions
            ->whereHas('roles', fn ($role) => $role
                ->where('roles.id', $this->role_id)
                ->where('roles.account_id', $this->account_id))
            ->whereIn('scope', ['account', 'self'])
            ->where(fn ($query) => $query
                ->whereNull('account_id')
                ->orWhere('account_id', $this->account_id))
            ->pluck('slug')
            ->map(fn ($slug): string => (string) $slug)
            ->all();
    }

    public function applyIdentity(UserIdentityDTO $identity): void
    {
        $this->fill(['name' => $identity->name, 'email' => $identity->email]);

        if ($this->exists && $this->isDirty('email')) {
            $this->email_verified_at = null;
        }
    }

    public function isLastAdministratorWithAccess(): bool
    {
        if ($this->role?->system_key !== 'administrator') {
            return false;
        }

        $administratorsWithAccess = self::where('account_id', $this->account_id)
            ->where('role_id', $this->role_id)
            ->where('access_enabled', true)
            ->count();

        return $administratorsWithAccess <= 1;
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

    /** @param array<string, mixed> $options */
    public function save(array $options = []): bool
    {
        return (bool) AuditLog::transaction(fn () => $this->saveIdentity($options));
    }

    /** @param array<string,mixed> $options */
    private function saveIdentity(array $options = []): bool
    {
        if ($this->account_id && ! $this->role_id) {
            [$admin, $member] = app(DefaultRolesAction::class)->execute($this->account_id);
            $this->role_id = $this->is_account_admin ? $admin->id : $member->id;
        }

        if ($this->getAttribute('person_id') && ! $this->isDirty(['name', 'email', 'account_id'])) {
            return parent::save($options);
        }

        return $this->getConnection()->transaction(function () use ($options): bool {
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
