<?php

namespace App\Domains\Pessoas\Application\Data;

use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Usuarios\Application\Data\ProvisionUserData;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class SavePersonData
{
    /** @param array<string,mixed> $values */
    public function __construct(
        public array $values,
        public ?ProvisionUserData $access,
        public bool $userSelected = false,
        public bool $supplierSelected = false,
        public bool $customerSelected = false,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     * @param  array<int,int>  $existingTagIds
     */
    public static function fromInput(
        array $input,
        int $accountId,
        bool $hasUser,
        array $existingTagIds = [],
        ?Person $existingPerson = null,
    ): self {
        $input['name'] = is_string($input['name'] ?? null) ? trim($input['name']) : null;

        // Telefone ausente no envio mantém o valor atual da pessoa.
        if (! array_key_exists('phone', $input) && $existingPerson !== null) {
            $input['phone'] = $existingPerson->phone;
        }

        if (is_string($input['phone'] ?? null)) {
            $input['phone'] = preg_replace('/[.\/()\s-]+/', '', $input['phone']);
        }

        if (! array_key_exists('email', $input) && $existingPerson !== null) {
            $input['email'] = $existingPerson->email;
        }

        $input['email'] = is_string($input['email'] ?? null) ? mb_strtolower(trim($input['email'])) : null;

        // Etiquetas inativas só são aceitas quando a pessoa já as possui.
        $selectableTag = Rule::exists('tags', 'id')
            ->where('account_id', $accountId)
            ->where('module', 'people')
            ->where(fn ($query) => $query
                ->where('active', true)
                ->orWhereIn('id', $existingTagIds))
            ->where(fn ($query) => $query
                ->whereNull('system_key')
                ->orWhereIn('system_key', ['user', 'supplier', 'customer']));

        $values = Validator::validate($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', 'distinct', $selectableTag],
        ], [
            'name.required' => 'Informe o nome.',
            'name.string' => 'Informe um nome válido.',
            'name.max' => 'O nome deve ter até 255 caracteres.',
            'email.email' => 'Informe um e-mail válido.',
            'email.max' => 'O e-mail deve ter até 255 caracteres.',
            'phone.string' => 'Informe um telefone válido.',
            'phone.max' => 'O telefone deve ter até 30 caracteres.',
            'tag_ids.array' => 'Selecione etiquetas válidas.',
            'tag_ids.*' => 'Selecione etiquetas do módulo Pessoas desta conta.',
        ]);
        $tagIds = $values['tag_ids'] ?? [];

        $userSelected = self::selectsSpecialTag($accountId, $tagIds, 'user');
        $access = ! $hasUser && $userSelected
            ? ProvisionUserData::fromInput($input, true, $accountId)
            : null;

        $supplierSelected = self::selectsSpecialTag($accountId, $tagIds, 'supplier');
        $details = SavePersonDetailsData::fromInput($input, $accountId, $existingPerson);
        $customerSelected = self::selectsSpecialTag($accountId, $tagIds, 'customer');

        return new self(
            [...$values, ...$details->values],
            $access,
            $userSelected,
            $supplierSelected,
            $customerSelected,
        );
    }

    /** @param array<int,int> $tagIds */
    private static function selectsSpecialTag(int $accountId, array $tagIds, string $systemKey): bool
    {
        return Tag::where('account_id', $accountId)
            ->where('module', 'people')
            ->where('system_key', $systemKey)
            ->whereIn('id', $tagIds)
            ->exists();
    }
}
