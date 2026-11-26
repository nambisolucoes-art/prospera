<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Data\Categories;

use App\Support\NormalizedName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Cadastro de categoria pública da conta: nome único, slug único e conteúdo de SEO em texto simples. */
final readonly class CreatePublicCategoryData
{
    public function __construct(
        public int $accountId,
        public string $name,
        public string $normalizedName,
        public string $slug,
        public int $sortOrder,
        public ?string $seoTitle,
        public ?string $seoDescription,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input, int $accountId): self
    {
        $name = NormalizedName::display((string) ($input['name'] ?? ''));
        $input['name'] = $name;
        $input['slug'] = Str::slug((string) (($input['slug'] ?? '') !== '' ? $input['slug'] : $name));

        $values = Validator::validate($input, [
            'name' => ['required', 'string', 'max:80'],
            'slug' => [
                'required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('public_categories', 'slug')->where('account_id', $accountId),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:160'],
        ], [
            'name.required' => 'Informe o nome da categoria.',
            'name.max' => 'O nome deve ter até 80 caracteres.',
            'slug.required' => 'Informe um endereço válido.',
            'slug.regex' => 'Use só letras minúsculas, números e hífens no endereço.',
            'slug.unique' => 'Já existe uma categoria com este endereço.',
            'seo_title.max' => 'O título deve ter até 70 caracteres.',
            'seo_description.max' => 'A descrição deve ter até 160 caracteres.',
        ]);

        $normalized = NormalizedName::key($values['name']);

        if (DB::table('public_categories')->where('account_id', $accountId)->where('normalized_name', $normalized)->exists()) {
            throw ValidationException::withMessages(['name' => 'Já existe uma categoria com este nome.']);
        }

        return new self(
            accountId: $accountId,
            name: $values['name'],
            normalizedName: $normalized,
            slug: $values['slug'],
            sortOrder: (int) ($values['sort_order'] ?? 0),
            seoTitle: self::plain($values['seo_title'] ?? null),
            seoDescription: self::plain($values['seo_description'] ?? null),
        );
    }

    private static function plain(?string $value): ?string
    {
        $text = $value === null ? '' : trim(strip_tags($value));

        return $text === '' ? null : $text;
    }
}
