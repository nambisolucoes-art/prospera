<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Actions;

use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Acessos\Domain\Models\RoleAssignment;
use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Auditoria\Application\Actions\AuditContext;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Pessoas\Domain\DTOs\People\CreatePersonDTO;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Models\User;

final readonly class RegisterAccountMembershipAction
{
    public function __construct(private AuditContext $audit) {}

    /** $grantedBy identifica quem concedeu o primeiro cargo quando não é o autor da requisição (aceite de convite). */
    public function execute(
        User $user,
        Role $role,
        AccessNode $node,
        ?Person $person = null,
        ?int $grantedBy = null,
    ): AccountMembership {
        return AuditLog::transaction(function () use ($user, $role, $node, $person, $grantedBy): AccountMembership {
            if ($person === null) {
                $data = new CreatePersonDTO(
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

            $grantor = $grantedBy ?? $this->audit->actorId ?? auth()->user()?->id;
            $assignment = RoleAssignment::grant($membership, $role, $node, $grantor);
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
