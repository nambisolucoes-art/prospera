<?php

namespace App\Domains\Pessoas\Application\Actions;

use App\Domains\Acessos\Application\Actions\SetMembershipStatusAction;
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Pessoas\Application\Data\SavePersonData;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;

final readonly class SavePersonAccessAction
{
    public function __construct(private SetMembershipStatusAction $status) {}

    public function execute(Client $account, Person $person, User $user, SavePersonData $data): void
    {
        $membership = AccountMembership::where('account_id', $account->id)
            ->where('person_id', $person->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $hadUserTag = $person->tags()->where('system_key', 'user')->exists();

        if ($hadUserTag !== $data->userSelected && ($membership->status === 'active') !== $data->userSelected) {
            $this->status->execute($account->id, $membership->id, $data->userSelected);
        }
    }
}
