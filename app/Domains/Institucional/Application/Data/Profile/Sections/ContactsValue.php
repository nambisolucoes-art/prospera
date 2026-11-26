<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Data\Profile\Sections;

use App\Domains\Institucional\Domain\Contracts\SectionValue;
use App\Support\ContactInput;
use Illuminate\Support\Facades\Validator;

/**
 * Contatos destinados à divulgação pública, sempre digitados. Nunca vêm de Pessoas, do colaborador nem dos contatos
 * operacionais do estabelecimento (DA2-23). Reaproveita a normalização de `ContactInput`.
 */
final readonly class ContactsValue implements SectionValue
{
    /**
     * @param  list<string>  $phones
     * @param  list<string>  $whatsapps
     * @param  list<string>  $emails
     */
    public function __construct(
        public array $phones,
        public array $whatsapps,
        public array $emails,
    ) {}

    public static function fromInput(array $input, array $current): static
    {
        $merged = array_replace(self::empty()->toArray(), $current, array_intersect_key($input, self::empty()->toArray()));

        foreach (['phones', 'whatsapps'] as $field) {
            $merged[$field] = array_values(array_filter(
                array_map(
                    fn (mixed $item) => is_string($item) ? (ContactInput::normalize(['phone' => $item])['phone'] ?? null) : null,
                    is_array($merged[$field] ?? null) ? $merged[$field] : [],
                ),
                fn (?string $item) => $item !== null,
            ));
        }

        $merged['emails'] = array_values(array_filter(
            array_map(
                fn (mixed $item) => is_string($item) ? (ContactInput::normalize(['email' => $item])['email'] ?? null) : null,
                is_array($merged['emails'] ?? null) ? $merged['emails'] : [],
            ),
            fn (?string $item) => $item !== null,
        ));

        $validated = Validator::validate($merged, [
            'phones' => ['array', 'max:5'],
            'phones.*' => ['string', 'regex:/^\+?\d{8,15}$/'],
            'whatsapps' => ['array', 'max:5'],
            'whatsapps.*' => ['string', 'regex:/^\+?\d{8,15}$/'],
            'emails' => ['array', 'max:5'],
            'emails.*' => ['email', 'max:255'],
        ], [
            'phones.max' => 'Informe até 5 telefones.',
            'phones.*.regex' => 'Informe um telefone válido, com DDD.',
            'whatsapps.max' => 'Informe até 5 números de WhatsApp.',
            'whatsapps.*.regex' => 'Informe um WhatsApp válido, com DDD.',
            'emails.max' => 'Informe até 5 e-mails.',
            'emails.*.email' => 'Informe um e-mail válido.',
        ]);

        return new self($validated['phones'], $validated['whatsapps'], $validated['emails']);
    }

    public static function empty(): static
    {
        return new self([], [], []);
    }

    public static function default(): static
    {
        return self::empty();
    }

    public function toArray(): array
    {
        return ['phones' => $this->phones, 'whatsapps' => $this->whatsapps, 'emails' => $this->emails];
    }
}
