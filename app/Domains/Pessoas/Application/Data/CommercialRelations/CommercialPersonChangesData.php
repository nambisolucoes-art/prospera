<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Data\CommercialRelations;

use App\Domains\Pessoas\Application\Data\People\UpdatePersonData;
use App\Domains\Pessoas\Domain\Models\Person;
use Illuminate\Validation\ValidationException;

/**
 * Alterações dos dados centrais da pessoa pedidas junto com a relação comercial. Só ganham valores validados
 * depois que a Action trava a pessoa, porque a validação preenche os campos ausentes com os atuais.
 */
final readonly class CommercialPersonChangesData
{
    /** @param array<string,mixed> $fields */
    public function __construct(
        public array $fields,
        public bool $allowIdentificationChange,
    ) {}

    /** @param array<string,mixed> $input */
    public static function fromInput(array $input, bool $allowIdentificationChange): self
    {
        $allowed = array_flip([...Person::IDENTIFICATION_FIELDS, ...Person::CONTACT_FIELDS]);

        return new self(
            fields: array_intersect_key($input, $allowed),
            allowIdentificationChange: $allowIdentificationChange,
        );
    }

    public function isEmpty(): bool
    {
        return $this->fields === [];
    }

    /** @throws ValidationException */
    public function validatedFor(Person $lockedPerson): UpdatePersonData
    {
        return UpdatePersonData::forCommercialReuse($this->fields, $lockedPerson, $this->allowIdentificationChange);
    }
}
