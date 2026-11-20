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
    public function __construct(public array $values, public ?ProvisionUserData $access, public bool $supplierSelected = false, public bool $customerSelected = false) {}

    /**
     * @param  array<string,mixed>  $input
     * @param  array<int,int>  $existingTagIds
     */
    public static function fromInput(array $input, int $accountId, bool $hasUser, array $existingTagIds = [], ?Person $existingPerson = null): self
    {
        $input['name'] = is_string($input['name'] ?? null) ? trim($input['name']) : null;
        if (is_string($input['phone'] ?? null)) {
            $input['phone'] = preg_replace('/[.\/()\s-]+/', '', $input['phone']);
        }
        $input['email'] = is_string($input['email'] ?? null) ? mb_strtolower(trim($input['email'])) : null;
        $values = Validator::validate($input, [
            'name' => ['required', 'string', 'max:255'], 'email' => [$hasUser ? 'required' : 'nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'],
            'role_id' => ['sometimes', 'integer', Rule::exists('roles', 'id')->where('account_id', $accountId)],
            'tag_ids' => ['sometimes', 'array'], 'tag_ids.*' => ['integer', 'distinct', Rule::exists('tags', 'id')->where('account_id', $accountId)->where('module', 'people')->where(fn ($q) => $q->where('active', true)->orWhereIn('id', $existingTagIds))->where(fn ($q) => $q->whereNull('system_key')->orWhereIn('system_key', ['user', 'supplier', 'customer']))],
        ], [
            'role_id.exists' => 'Selecione um cargo desta conta.',
            'role_id.integer' => 'Selecione um cargo válido.',
            'name.required' => 'Informe o nome.',
            'name.string' => 'Informe um nome válido.',
            'name.max' => 'O nome deve ter até 255 caracteres.',
            'email.required' => 'Informe o e-mail para o acesso de usuário.',
            'email.email' => 'Informe um e-mail válido.',
            'email.max' => 'O e-mail deve ter até 255 caracteres.',
            'phone.string' => 'Informe um telefone válido.',
            'phone.max' => 'O telefone deve ter até 30 caracteres.',
            'tag_ids.array' => 'Selecione etiquetas válidas.',
            'tag_ids.*' => 'Selecione etiquetas do módulo Pessoas desta conta.',
        ]);
        $selectedUser = Tag::where('account_id', $accountId)->where('module', 'people')->where('system_key', 'user')->whereIn('id', $values['tag_ids'] ?? [])->exists();
        $access = ! $hasUser && $selectedUser ? ProvisionUserData::fromInput($input, true, $accountId) : null;

        $supplierSelected = Tag::where('account_id', $accountId)->where('module', 'people')->where('system_key', 'supplier')->whereIn('id', $values['tag_ids'] ?? [])->exists();
        $details = SavePersonDetailsData::fromInput($input, $accountId, $existingPerson);

        $customerSelected = Tag::where('account_id', $accountId)->where('module', 'people')->where('system_key', 'customer')->whereIn('id', $values['tag_ids'] ?? [])->exists();

        return new self([...$values, ...$details->values], $access, $supplierSelected, $customerSelected);
    }
}
