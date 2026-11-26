<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Data\Profile\Sections;

use App\Domains\Institucional\Domain\Contracts\SectionValue;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Redes sociais: no máximo um link por rede, sempre HTTPS e só no domínio da própria rede. */
final readonly class SocialLinksValue implements SectionValue
{
    /** @param list<array{network: string, url: string}> $items */
    public function __construct(public array $items) {}

    public static function fromInput(array $input, array $current): static
    {
        $items = array_key_exists('items', $input) ? $input['items'] : ($current['items'] ?? []);
        $items = array_values(array_filter(is_array($items) ? $items : [], fn (mixed $item) => is_array($item)
            && trim((string) ($item['url'] ?? '')) !== ''));

        $networks = config('institutional.social_networks');

        $validated = Validator::validate(['items' => array_map(fn (array $item) => [
            'network' => (string) ($item['network'] ?? ''),
            'url' => trim((string) $item['url']),
        ], $items)], [
            'items' => ['array', 'max:'.count($networks)],
            'items.*.network' => ['required', 'string', 'in:'.implode(',', array_keys($networks))],
            'items.*.url' => ['required', 'string', 'max:255', 'url:https'],
        ], [
            'items.max' => 'Há mais redes do que o permitido.',
            'items.*.network.in' => 'Escolha uma rede da lista.',
            'items.*.network.required' => 'Escolha uma rede da lista.',
            'items.*.url.url' => 'Informe um link começando com https://.',
            'items.*.url.required' => 'Informe o link da rede.',
            'items.*.url.max' => 'O link deve ter até 255 caracteres.',
        ]);

        $errors = [];
        $seen = [];

        foreach ($validated['items'] as $index => $item) {
            $host = strtolower((string) parse_url($item['url'], PHP_URL_HOST));
            $host = preg_replace('/^www\./', '', $host) ?? $host;

            $allowed = array_filter(
                $networks[$item['network']]['hosts'],
                fn (string $domain) => $host === $domain || str_ends_with($host, '.'.$domain),
            );

            if ($allowed === []) {
                $errors["value.items.$index.url"] = 'O link deve ser do domínio da rede escolhida.';
            }

            if (in_array($item['network'], $seen, true)) {
                $errors["value.items.$index.network"] = 'Cada rede só pode aparecer uma vez.';
            }

            $seen[] = $item['network'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return new self($validated['items']);
    }

    public static function empty(): static
    {
        return new self([]);
    }

    public static function default(): static
    {
        return self::empty();
    }

    public function toArray(): array
    {
        return ['items' => $this->items];
    }
}
