<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Data\Categories;

use App\Support\NormalizedName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Edição de categoria pública: os mesmos campos do cadastro mais a situação. Sem exclusão: só inativar. */
final readonly class UpdatePublicCategoryData
{
    public function __construct(
        public int $accountId,
        public string $name,
        public string $normalizedName,
        public string $slug,
        public int $sortOrder,
        public ?string $seoTitle,
        public ?string $seoDescription,
        public bool $active,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input, int $accountId, int $categoryId): self
    {
        $input['name'] = NormalizedName::display((string) ($input['name'] ?? ''));

        $values = Validator::validate($input, [
            'name' => ['required', 'string', 'max:80'],
            'slug' => [
                'required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('public_categories', 'slug')->where('account_id', $accountId)->ignore($categoryId),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:160'],
            'active' => ['required', 'boolean'],
        ], [
            'name.required' => 'Informe o nome da categoria.',
            'name.max' => 'O nome deve ter até 80 caracteres.',
            'slug.required' => 'Informe um endereço válido.',
            'slug.regex' => 'Use só letras minúsculas, números e hífens no endereço.',
            'slug.unique' => 'Já existe uma categoria com este endereço.',
            'seo_title.max' => 'O título deve ter até 70 caracteres.',
            'seo_description.max' => 'A descrição deve ter até 160 caracteres.',
            'active.required' => 'Informe a situação.',
        ]);

        $normalized = NormalizedName::key($values['name']);

        $taken = DB::table('public_categories')
            ->where('account_id', $accountId)
            ->where('normalized_name', $normalized)
            ->where('id', '!=', $categoryId)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['name' => 'Já existe uma categoria com este nome.']);
        }

        $plain = static fn (?string $value): ?string => $value === null || trim(strip_tags($value)) === ''
            ? null
            : trim(strip_tags($value));

        return new self(
            accountId: $accountId,
            name: $values['name'],
            normalizedName: $normalized,
            slug: $values['slug'],
            sortOrder: (int) ($values['sort_order'] ?? 0),
            seoTitle: $plain($values['seo_title'] ?? null),
            seoDescription: $plain($values['seo_description'] ?? null),
            active: (bool) $values['active'],
        );
    }
}
