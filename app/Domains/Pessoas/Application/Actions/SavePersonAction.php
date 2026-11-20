<?php

namespace App\Domains\Pessoas\Application\Actions;

use App\Domains\Acessos\Application\Actions\AssignRoleAction;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Clientes\Application\Actions\SaveCustomerAction;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Fornecedores\Application\Actions\SaveSupplierAction;
use App\Domains\Pessoas\Application\Data\SavePersonData;
use App\Domains\Pessoas\Domain\DTOs\PersonDetailsDTO;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Usuarios\Application\Actions\CreateUserAction;
use App\Domains\Usuarios\Domain\DTOs\UserIdentityDTO;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SavePersonAction
{
    public function execute(Client $account, ?Person $person, SavePersonData $data): Person
    {
        return AuditLog::transaction(function () use ($account, $person, $data) {
            $person = $person ? Person::where('account_id', $account->id)->lockForUpdate()->findOrFail($person->id) : new Person(['account_id' => $account->id]);
            $before = $person->exists ? $this->snapshot($person) : null;
            $account->newQuery()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $original = $person->only(['name', 'email', 'phone']);
            $details = new PersonDetailsDTO(array_diff_key($data->values, array_flip(['tag_ids', 'role_id'])));
            $person->fill($details->attributes);
            $user = $person->user()->first();
            if ($user) {
                $enabled = Tag::where('account_id', $account->id)->where('system_key', 'user')->whereIn('id', $data->values['tag_ids'] ?? [])->exists();
                if ($user->access_enabled && ! $enabled) {
                    Gate::authorize('account.users.revoke');
                    if ($user->role?->system_key === 'administrator' && User::where('account_id', $account->id)->where('role_id', $user->role_id)->where('access_enabled', true)->count() <= 1) {
                        throw ValidationException::withMessages(['tag_ids' => 'Mantenha ao menos um administrador da conta com acesso.']);
                    }
                } elseif (! $user->access_enabled && $enabled) {
                    Gate::authorize('account.users.create');
                    app(AssignRoleAction::class)->assertAssignable($account->id, (int) ($data->values['role_id'] ?? $user->role_id));
                }
                $user->access_enabled = $enabled;
                if ($person->isDirty(['name', 'email'])) {
                    Gate::authorize('account.users.update');
                }
                if (User::whereRaw('lower(email) = ?', [$person->email])->where('id', '!=', $user->id)->exists()) {
                    throw ValidationException::withMessages(['email' => 'Já existe um usuário com este e-mail.']);
                }
                $user->applyIdentity(new UserIdentityDTO($person->name, $person->email));
                $user->saveOrFail();
            }
            if ($data->access || ($user && isset($data->values['role_id']) && (int) $data->values['role_id'] !== $user->role_id)) {
                Gate::authorize('account.users.assign-role');
                $roleId = $data->access->roleId ?? (int) $data->values['role_id'];
                app(AssignRoleAction::class)->assertAssignable($account->id, $roleId);
                if ($user) {
                    app(AssignRoleAction::class)->execute($user, $roleId);
                }
            }
            if ($person->document && Person::where('account_id', $account->id)->where('document', $person->document)->when($person->exists, fn ($query) => $query->whereKeyNot($person->id))->exists()) {
                throw ValidationException::withMessages(['document' => 'Já existe uma pessoa com este documento nesta conta.']);
            }
            $person->saveOrFail();
            if ($data->access) {
                Gate::authorize('account.users.create');
                if ($user) {
                    throw ValidationException::withMessages(['tag_ids' => 'Esta pessoa já possui acesso. Atualize o cadastro antes de continuar.']);
                }
                $user = app(CreateUserAction::class)->execute($data->access->user, $data->access->role, $account, $person, $data->access->roleId);
            }
            app(SaveSupplierAction::class)->execute($person, $data->supplierSelected);
            app(SaveCustomerAction::class)->execute($person, $data->customerSelected);
            $ids = $data->values['tag_ids'] ?? [];
            // Lock definitions before syncing so deletion cannot race a new link.
            $tags = Tag::where('account_id', $account->id)->whereIn('id', $ids)->lockForUpdate()->get();
            $existingIds = $person->tags()->pluck('tags.id')->all();
            if ($tags->count() !== count($ids) || $tags->contains(fn ($tag) => ! $tag->active && ! in_array($tag->id, $existingIds, true))) {
                throw ValidationException::withMessages(['tag_ids' => 'Selecione etiquetas ativas desta conta.']);
            }
            $person->tags()->sync($ids);
            $after = $this->snapshot($person);
            AuditLog::relationship($person, 'tag_ids', $before['tag_ids'] ?? [], $after['tag_ids'], 'Etiquetas');
            if ($before !== $after || $original !== $person->only(['name', 'email', 'phone'])) {
                DirectoryAudit::record($account->id, $before === null ? 'person.created' : 'person.updated', 'person', $person->id, ['before' => $before, 'after' => $after, 'changed_fields' => array_keys(array_diff_assoc($person->only(['name', 'email', 'phone']), $original))]);
            }

            return $person;
        });
    }

    /** @return array<string,mixed> */
    private function snapshot(Person $person): array
    {
        return ['tag_ids' => $person->tags()->orderBy('tags.id')->pluck('tags.id')->all(), 'user_id' => $person->user()->value('id')];
    }
}
