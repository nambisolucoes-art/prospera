<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Data\CommercialRelations;

use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Pessoas\Domain\Enums\CommercialRole;
use Illuminate\Validation\ValidationException;

/** Edição de uma relação comercial: dados centrais, etiquetas, situação e empresas incluídas ou removidas. */
final readonly class UpdateCommercialRelationData
{
    /**
     * @param  list<string>  $addCompanies
     * @param  list<string>  $removeCompanies
     */
    public function __construct(
        public CommercialRole $role,
        public int $relationId,
        public ?CommercialTagsData $tags,
        public CommercialPersonChangesData $centralChanges,
        public ?bool $active,
        public array $addCompanies,
        public array $removeCompanies,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input, CommercialRole $role, Company $company, int $relationId): self
    {
        $editsTags = array_key_exists('tag_ids', $input) || filter_var($input['edit_tags'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $companies = CommercialRelationLinkData::companies($input, $company->code);

        return new self(
            role: $role,
            relationId: $relationId,
            tags: $editsTags ? CommercialTagsData::fromInput($input['tag_ids'] ?? null) : null,
            centralChanges: CommercialPersonChangesData::fromInput($input, allowIdentificationChange: true),
            active: CommercialRelationLinkData::optionalStatus($input),
            addCompanies: $companies['add'],
            removeCompanies: $companies['remove'],
        );
    }
}
