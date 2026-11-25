<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Application\Data\Accounts;

use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Dados da própria conta, editados por quem a administra. Documento, plano e situação não mudam aqui. */
final readonly class UpdateAccountProfileData
{
    private const STATES = 'AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO';

    /** @param  array<string,mixed>|null  $details */
    public function __construct(
        public string $name,
        public ?string $email,
        public ?array $details,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(#[\SensitiveParameter] array $input, Client $client): self
    {
        $input = Arr::only($input, ['name', 'email', 'details']);

        $input = self::normalize($input);

        $validated = Validator::validate($input, self::rules($client), self::messages());

        foreach (['additional_phones', 'additional_emails'] as $field) {
            if (isset($validated['details'][$field])) {
                $validated['details'][$field] = array_values(array_filter(
                    $validated['details'][$field],
                    static fn (?string $value): bool => filled($value),
                ));
            }
        }

        return new self(
            name: $validated['name'],
            email: array_key_exists('email', $validated) ? $validated['email'] : $client->email,
            details: $validated['details'] ?? null,
        );
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    private static function normalize(#[\SensitiveParameter] array $input): array
    {
        foreach (['name', 'email'] as $field) {
            if (is_string($input[$field] ?? null)) {
                $input[$field] = trim($input[$field]);
            }
        }

        if (! is_array($input['details'] ?? null)) {
            return $input;
        }

        $normalize = static fn (string $value): string => preg_replace('/[.\/()\s-]+/', '', $value) ?? $value;

        if (is_string($input['details']['address']['postal_code'] ?? null)) {
            $input['details']['address']['postal_code'] = $normalize($input['details']['address']['postal_code']);
        }

        foreach (['phone', 'whatsapp'] as $field) {
            if (is_string($input['details'][$field] ?? null)) {
                $input['details'][$field] = $normalize($input['details'][$field]);
            }
        }

        foreach (['additional_phones' => $normalize, 'additional_emails' => 'trim'] as $field => $sanitize) {
            if (is_array($input['details'][$field] ?? null)) {
                $input['details'][$field] = array_map(
                    static fn (mixed $value): mixed => is_string($value) ? $sanitize($value) : $value,
                    $input['details'][$field],
                );
            }
        }

        return $input;
    }

    /** @return array<string,list<mixed>> */
    public static function rules(Client $client): array
    {
        $company = $client->person_type === 'company';

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'details' => ['sometimes', 'array:trade_name,address,phone,whatsapp,additional_phones,additional_emails'],
            'details.trade_name' => [Rule::excludeIf(! $company), 'nullable', 'string', 'max:255'],
            'details.address' => ['sometimes', 'array:postal_code,street,number,complement,neighborhood,city,state'],
            'details.address.postal_code' => ['nullable', 'digits:8'],
            'details.address.street' => ['nullable', 'string', 'max:255'],
            'details.address.number' => ['nullable', 'string', 'max:10'],
            'details.address.complement' => ['nullable', 'string', 'max:255'],
            'details.address.neighborhood' => ['nullable', 'string', 'max:255'],
            'details.address.city' => ['nullable', 'string', 'max:255'],
            'details.address.state' => ['nullable', Rule::in(explode(' ', self::STATES))],
            'details.whatsapp' => ['nullable', 'regex:/^\d{10,11}$/'],
            'details.additional_phones' => ['sometimes', 'array', 'list', 'max:20'],
            'details.additional_phones.*' => ['nullable', 'string', 'regex:/^\d{10,11}$/'],
            'details.additional_emails' => ['sometimes', 'array', 'list', 'max:20'],
            'details.additional_emails.*' => ['nullable', 'string', 'email', 'max:255'],
            'details.phone' => ['nullable', 'regex:/^\d{10,11}$/'],
        ];
    }

    /** @return array<string,string|array<string,string>> */
    public static function messages(): array
    {
        return [
            'string' => 'Informe um texto válido.',
            'max' => ['string' => 'Use até :max caracteres.'],
            'array' => 'Revise as informações deste grupo.',
            'list' => 'Informe uma lista válida de contatos.',
            'name.required' => 'Informe o nome ou a razão social.',
            'email.email' => 'Informe um e-mail de contato válido.',
            'details.address.postal_code.digits' => 'Informe um CEP com 8 dígitos.',
            'details.address.state.in' => 'Selecione um estado válido.',
            'details.phone.required' => 'Informe um telefone.',
            'details.phone.regex' => 'Informe um telefone com DDD e 10 ou 11 dígitos.',
            'details.whatsapp.regex' => 'Informe um WhatsApp com DDD e 10 ou 11 dígitos.',
            'details.additional_phones.max' => 'Adicione até 20 telefones adicionais.',
            'details.additional_phones.*.regex' => 'Informe um telefone com DDD e 10 ou 11 dígitos.',
            'details.additional_emails.max' => 'Adicione até 20 e-mails adicionais.',
            'details.additional_emails.*.email' => 'Informe um e-mail válido.',
        ];
    }
}
