<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Data\CommercialRelations;

use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Pessoas\Application\Data\People\CreatePersonData;
use App\Domains\Pessoas\Domain\Enums\CommercialRole;
use App\Domains\Pessoas\Domain\Models\Person;
use Illuminate\Validation\ValidationException;

/** Cadastro de uma relação comercial: pessoa nova ou existente, etiquetas e empresas adicionais. */
final readonly class RegisterCommercialRelationData
{
    /** @param list<string> $additionalCompanies */
    public function __construct(
        public CommercialRole $role,
        public int|CreatePersonData $person,
        public CommercialTagsData $tags,
        public CommercialPersonChangesData $centralChanges,
        public array $additionalCompanies,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input, CommercialRole $role, Company $company, int $accountId): self
    {
        $tags = CommercialTagsData::fromInput($input['tag_ids'] ?? null);
        $personFields = array_intersect_key($input, array_flip([...Person::IDENTIFICATION_FIELDS, ...Person::CONTACT_FIELDS]));

        if (array_key_exists('person_id', $input)) {
            $person = CommercialRelationLinkData::personId($input['person_id']);
            $centralChanges = CommercialPersonChangesData::fromInput($personFields, allowIdentificationChange: false);
        } else {
            $person = CreatePersonData::fromInput($personFields, $accountId);
            $centralChanges = CommercialPersonChangesData::fromInput([], allowIdentificationChange: false);
        }

        $companies = CommercialRelationLinkData::companies($input, $company->code);

        if ($companies['remove'] !== []) {
            throw ValidationException::withMessages(['remove_companies' => 'Revise a seleção de empresas.']);
        }

        return new self($role, $person, $tags, $centralChanges, $companies['add']);
    }
}
