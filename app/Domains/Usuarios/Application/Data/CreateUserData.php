<?php

declare(strict_types=1);

namespace App\Domains\Usuarios\Application\Data;

use App\Domains\Usuarios\Domain\Enums\PasswordSetup;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

final readonly class CreateUserData
{
    public string $name;

    public string $email;

    public function __construct(
        string $name,
        string $email,
        public PasswordSetup $setup,
        #[\SensitiveParameter] public ?string $password,
    ) {
        $this->name = trim($name);
        $this->email = Str::lower(trim($email));
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(#[\SensitiveParameter] array $input, int $minimumPasswordLength = 0): self
    {
        $rules = self::rules();
        if ($minimumPasswordLength > 0) {
            $rules['password'][] = 'min:'.$minimumPasswordLength;
        }

        return self::fromValidated(Validator::validate(self::normalize($input), $rules, self::messages()));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(#[\SensitiveParameter] array $input): array
    {
        if (is_string($input['name'] ?? null)) {
            $input['name'] = trim($input['name']);
        }

        if (is_string($input['email'] ?? null)) {
            $input['email'] = Str::lower(trim($input['email']));
        }

        return $input;
    }

    /** @param array<string, mixed> $data */
    public static function fromValidated(#[\SensitiveParameter] array $data): self
    {
        $name = $data['name'] ?? null;
        $email = $data['email'] ?? null;
        $mode = $data['mode'] ?? null;
        $password = $data['password'] ?? null;
        $setup = is_string($mode) ? PasswordSetup::tryFrom($mode) : null;

        if (! is_string($name) || ! is_string($email) || $setup === null
            || ($password !== null && ! is_string($password))) {
            throw new InvalidArgumentException('O cadastro de usuário exige dados validados.');
        }

        return new self($name, $email, $setup, $password);
    }

    /** @return array<string, list<mixed>> */
    public static function rules(string $prefix = ''): array
    {
        $mode = $prefix.'mode';

        return [
            $prefix.'name' => ['required', 'string', 'max:255'],
            $prefix.'email' => ['required', 'email', 'max:255'],
            $mode => ['required', Rule::enum(PasswordSetup::class)],
            $prefix.'password' => [
                "exclude_unless:$mode,manual",
                'required',
                'string',
                'max:72',
                Password::default(),
                'confirmed',
            ],
            $prefix.'password_confirmation' => ["exclude_unless:$mode,manual", 'required', 'string'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(string $prefix = ''): array
    {
        return [
            $prefix.'name.required' => $prefix === '' ? 'Informe o nome.' : 'Informe o nome do administrador.',
            $prefix.'name.string' => 'Informe um nome válido.',
            $prefix.'name.max' => 'O nome deve ter até :max caracteres.',
            $prefix.'email.required' => $prefix === '' ? 'Informe o e-mail.' : 'Informe o e-mail do administrador.',
            $prefix.'email.email' => 'Informe um e-mail válido.',
            $prefix.'email.max' => 'O e-mail deve ter até :max caracteres.',
            $prefix.'mode.required' => 'Selecione a forma de acesso.',
            $prefix.'mode.enum' => 'Selecione uma forma de acesso válida.',
            $prefix.'password.required' => 'Informe a senha inicial.',
            $prefix.'password.string' => 'Informe uma senha válida.',
            $prefix.'password.min' => 'A senha deve ter pelo menos :min caracteres.',
            $prefix.'password.max' => 'A senha deve ter até :max caracteres.',
            $prefix.'password.confirmed' => 'As senhas precisam ser iguais.',
            $prefix.'password.password.mixed' => 'Use letras maiúsculas e minúsculas na senha.',
            $prefix.'password.password.letters' => 'Inclua pelo menos uma letra na senha.',
            $prefix.'password.password.numbers' => 'Inclua pelo menos um número na senha.',
            $prefix.'password.password.symbols' => 'Inclua pelo menos um símbolo na senha.',
            $prefix.'password.password.uncompromised' => 'Esta senha apareceu em um vazamento de dados. Escolha outra.',
            $prefix.'password_confirmation.required' => 'Confirme a senha inicial.',
            $prefix.'password_confirmation.string' => 'Informe a confirmação de senha.',
        ];
    }
}
