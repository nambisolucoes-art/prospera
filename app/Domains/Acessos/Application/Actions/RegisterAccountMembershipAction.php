<?php

namespace App\Domains\Acessos\Application\Actions;

use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Acessos\Domain\Models\RoleAssignment;
use App\Domains\Auditoria\Application\Actions\AuditContext;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Pessoas\Domain\DTOs\PersonDTO;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Models\User;

final readonly class RegisterAccountMembershipAction
{
    public function __construct(private AuditContext $audit) {}

    public function execute(User $user, Role $role, AccessNode $node, ?Person $person = null): AccountMembership
    {
        return AuditLog::transaction(function () use ($user, $role, $node, $person): AccountMembership {
            if ($person === null) {
                $data = new PersonDTO(
                    name: $user->name,
                    email: $user->email,
                    phone: null,
                    personType: 'individual',
                    document: null,
                    tradeName: null,
                    stateRegistration: null,
                    municipalRegistration: null,
                    contactName: null,
                    whatsapp: null,
                    website: null,
                    address: null,
                    additionalPhones: [],
                    additionalEmails: [],
                );
                $person = Person::register($data, $node->accountId);
                $person->saveOrFail();
            }

            $membership = AccountMembership::register($user, $node->accountId, $person->id);
            $membership->saveOrFail();

            $assignment = RoleAssignment::grant($membership, $role, $node, $this->audit->actorId ?? auth()->user()?->id);
            $assignment->saveOrFail();

            Tag::supplierTag($node->accountId);
            Tag::customerTag($node->accountId);
            $tag = Tag::userTag($node->accountId);
            $before = $person->tags()->orderBy('tags.id')->pluck('tags.id')->all();
            $person->tags()->syncWithoutDetaching([$tag->id]);
            $after = $person->tags()->orderBy('tags.id')->pluck('tags.id')->all();
            $person->recordAuditFieldChange('tag_ids', $before, $after, 'Etiquetas');

            return $membership;
        });
    }
}
