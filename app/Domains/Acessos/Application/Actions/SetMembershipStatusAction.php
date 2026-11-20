<?php

namespace App\Domains\Acessos\Application\Actions;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class SetMembershipStatusAction
{
    public function __construct(private AccountContext $accounts, private EffectivePermissions $permissions) {}

    public function execute(int $accountId, int $membershipId, bool $active): void
    {
        abort_unless($this->accounts->requireId() === $accountId, 403);

        AuditLog::transaction(function () use ($accountId, $membershipId, $active): void {
            Client::whereKey($accountId)->lockForUpdate()->firstOrFail();
            $this->permissions->forget();

            $node = AccessNode::tenant($accountId);

            if ($active) {
                Gate::authorize('account.users.reactivate', $node);
            } else {
                Gate::authorize('account.users.revoke', $node);
            }

            $membership = AccountMembership::where('account_id', $accountId)->lockForUpdate()->findOrFail($membershipId);

            if (! $active && $membership->isLastActiveAdministrator()) {
                throw ValidationException::withMessages([
                    'tag_ids' => 'Mantenha ao menos um administrador da conta com acesso.',
                ]);
            }

            $membership->status = $active ? 'active' : 'suspended';
            $membership->saveOrFail();

            $person = $membership->person()->lockForUpdate()->firstOrFail();
            $tag = Tag::userTag($accountId);
            $before = $person->tags()->orderBy('tags.id')->pluck('tags.id')->all();

            if ($active) {
                $person->tags()->syncWithoutDetaching([$tag->id]);
            } else {
                $person->tags()->detach($tag->id);
            }

            $after = $person->tags()->orderBy('tags.id')->pluck('tags.id')->all();
            $person->recordAuditFieldChange('tag_ids', $before, $after, 'Etiquetas');
        });

        $this->accounts->forget();
        $this->permissions->forget();
    }
}
