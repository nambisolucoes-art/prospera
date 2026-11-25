<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Saneamento puro dos campos de contato e endereço recebidos nos cadastros: texto vazio vira `null`, telefones
 * ficam só com dígitos, e-mails em minúsculas e o endereço aparado. Não valida nada: as regras ficam em cada Data.
 */
final class ContactInput
{
    /**
     * @param  array<string,mixed>  $values
     * @return array<string,mixed>
     */
    public static function normalize(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($value)) {
                $values[$key] = trim($value) === '' ? null : trim($value);
            }
        }

        foreach (['phone', 'whatsapp'] as $field) {
            if (is_string($values[$field] ?? null)) {
                $values[$field] = preg_replace('/[.\/()\s-]+/', '', $values[$field]);
            }
        }

        if (is_string($values['email'] ?? null)) {
            $values['email'] = mb_strtolower($values['email']);
        }

        if (is_array($values['address'] ?? null)) {
            foreach ($values['address'] as $key => $value) {
                if (is_string($value)) {
                    $values['address'][$key] = trim($value) === '' ? null : trim($value);
                }
            }

            if (is_string($values['address']['postal_code'] ?? null)) {
                $values['address']['postal_code'] = preg_replace('/[\s-]/', '', $values['address']['postal_code']);
            }

            if (is_string($values['address']['state'] ?? null)) {
                $values['address']['state'] = strtoupper($values['address']['state']);
            }
        }

        foreach (['additional_phones', 'additional_emails'] as $field) {
            if (! is_array($values[$field] ?? null)) {
                continue;
            }

            $normalize = static function (mixed $value) use ($field): mixed {
                if (! is_string($value)) {
                    return $value;
                }

                if ($field === 'additional_phones') {
                    return preg_replace('/[.\/()\s-]+/', '', trim($value));
                }

                return mb_strtolower(trim($value));
            };

            $values[$field] = array_values(array_map($normalize, $values[$field]));
        }

        return $values;
    }

    /**
     * @param  array<int,mixed>|null  $list
     * @return list<mixed>
     */
    public static function filled(?array $list): array
    {
        return array_values(array_filter($list ?? [], static fn (mixed $value): bool => filled($value)));
    }
}
