<?php

namespace App\Models;

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
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
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
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function applyIdentity(UserIdentityDTO $identity): void
    {
        $this->fill(['name' => $identity->name, 'email' => $identity->email]);

        if ($this->exists && $this->isDirty('email')) {
            $this->email_verified_at = null;
        }
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
        if ($this->getAttribute('person_id') && ! $this->isDirty(['name', 'email'])) {
            return parent::save($options);
        }

        return $this->getConnection()->transaction(function () use ($options): bool {
            $person = $this->person()->first() ?? $this->person()->make();
            $person->fill(['name' => $this->name, 'email' => $this->email])->saveOrFail();
            $this->person()->associate($person);

            if (! parent::save($options)) {
                throw new \RuntimeException('Não foi possível salvar o usuário e sua pessoa.');
            }

            return true;
        });
    }
}
