<?php

namespace App\Models;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use App\Domains\Usuarios\Domain\DTOs\UserIdentityDTO;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
            'is_saas_admin' => 'Acesso à plataforma',
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
            'password' => 'hashed',
            'password_setup_pending' => 'boolean',
            'access_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    // Conta que está criando a identidade nesta operação; não é gravada e não concede acesso.
    private ?int $provisioningAccountId = null;

    public function provisionFor(?int $accountId): void
    {
        $this->provisioningAccountId = $accountId;
    }

    /**
     * Eventos da identidade (entrada, saída, perfil e segurança) não têm conta na URL (E09). Entram no
     * histórico da conta que a cria ou, depois, só quando a identidade participa de uma única conta; com
     * várias, ficam sem conta, sem escolher uma delas.
     */
    public function auditAccountId(): ?int
    {
        if ($this->provisioningAccountId !== null) {
            return $this->provisioningAccountId;
        }

        $accounts = $this->memberships()->limit(2)->pluck('account_id');

        return $accounts->count() === 1 ? (int) $accounts->first() : null;
    }

    /** @return HasMany<AccountMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(AccountMembership::class);
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

    public static function profilePhotoPath(int $userId, string $version): string
    {
        return "profile-photos/{$userId}/{$version}.webp";
    }
}
