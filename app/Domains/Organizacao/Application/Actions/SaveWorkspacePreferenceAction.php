<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Actions;

use App\Domains\Acessos\Application\Context\OrganizationContext;
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Organizacao\Application\Data\WorkspaceSelectionData;
use App\Models\User;
use App\Support\Exceptions\ResourceNotAvailable;

final readonly class SaveWorkspacePreferenceAction
{
    public function __construct(private OrganizationContext $context) {}

    public function execute(WorkspaceSelectionData $selection, User $user): void
    {
        $accountId = $this->context->accountId();
        $available = array_column($this->context->navigable($user), 'code');

        if (array_diff($selection->codes, $available) !== []) {
            throw new ResourceNotAvailable;
        }

        AccountMembership::query()
            ->where('user_id', $user->id)
            ->where('account_id', $accountId)
            ->where('status', 'active')
            ->toBase()
            ->update(['workspace_establishments' => json_encode($selection->codes, JSON_THROW_ON_ERROR)]);
    }
}
