<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Queries\Profile;

use App\Domains\Institucional\Domain\Enums\ProfileSectionType;
use App\Domains\Institucional\Domain\Models\ProfileSection;
use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;

/** A seção que o dono já gravou, dentro da conta. Sem linha, a seção equivale a `inherit` (ou ao padrão da empresa). */
final readonly class FindProfileSectionQuery
{
    public function execute(ProfileOwner $owner, ProfileSectionType $type): ?ProfileSection
    {
        return ProfileSection::where('account_id', $owner->accountId)
            ->where('company_id', $owner->companyId)
            ->where('section', $type->value)
            ->when(
                $owner->isEstablishment(),
                fn ($query) => $query->where('establishment_id', $owner->establishmentId),
                fn ($query) => $query->whereNull('establishment_id'),
            )
            ->first();
    }
}
