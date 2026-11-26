<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Queries\Profile;

use App\Domains\Institucional\Domain\Enums\ProfileSectionType;
use App\Domains\Institucional\Domain\ValueObjects\ProfileImpact;
use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;
use Illuminate\Support\Facades\DB;

/**
 * Impacto de publicar seções de um dono: quais têm diferença entre trabalho e publicado e, para a empresa, quantas
 * unidades ativas herdam o valor publicado (serão atingidas) e quantas têm `override` ou desativação (não serão).
 * Só conta: não devolve nomes de unidades, que podem estar fora do alcance de quem publica.
 */
final readonly class ProfileImpactQuery
{
    /** @param list<ProfileSectionType> $sections */
    public function execute(ProfileOwner $owner, array $sections): ProfileImpact
    {
        $names = array_map(fn (ProfileSectionType $type) => $type->value, $sections);

        $rows = DB::table('profile_sections')
            ->where('account_id', $owner->accountId)
            ->where('company_id', $owner->companyId)
            ->when(
                $owner->isEstablishment(),
                fn ($query) => $query->where('establishment_id', $owner->establishmentId),
                fn ($query) => $query->whereNull('establishment_id'),
            )
            ->whereIn('section', $names)
            ->get(['section', 'mode', 'value', 'published_mode', 'published_value']);

        $changed = [];

        foreach ($rows as $row) {
            $work = [$row->mode, $row->value === null ? null : json_decode((string) $row->value, true)];
            $published = [$row->published_mode, $row->published_value === null ? null : json_decode((string) $row->published_value, true)];

            if ($work !== $published) {
                $changed[] = ProfileSectionType::from($row->section);
            }
        }

        if ($owner->isEstablishment() || $changed === []) {
            return new ProfileImpact($changed, 0, 0);
        }

        $units = DB::table('establishments')
            ->where('account_id', $owner->accountId)
            ->where('company_id', $owner->companyId)
            ->where('active', true)
            ->pluck('id')
            ->all();

        $own = DB::table('profile_sections')
            ->where('account_id', $owner->accountId)
            ->where('company_id', $owner->companyId)
            ->whereNotNull('establishment_id')
            ->whereIn('section', array_map(fn (ProfileSectionType $type) => $type->value, $changed))
            ->whereIn('published_mode', ['override', 'disabled'])
            ->get(['establishment_id', 'section'])
            ->groupBy('establishment_id');

        $inheriting = 0;
        $overriding = 0;

        foreach ($units as $unit) {
            $overridden = count($own->get($unit, collect()));

            // Atingida se herda ao menos uma das seções alteradas; só "não afetada" se sobrescreve todas.
            if ($overridden < count($changed)) {
                $inheriting++;
            }

            if ($overridden > 0) {
                $overriding++;
            }
        }

        return new ProfileImpact($changed, $inheriting, $overriding);
    }
}
