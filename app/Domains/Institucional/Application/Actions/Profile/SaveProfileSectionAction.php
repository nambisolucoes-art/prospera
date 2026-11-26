<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Actions\Profile;

use App\Domains\Auditoria\Application\Actions\AuditContext;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Institucional\Application\Context\ProfileResolver;
use App\Domains\Institucional\Application\Data\Profile\SaveProfileSectionData;
use App\Domains\Institucional\Domain\DTOs\Profile\SaveProfileSectionDTO;
use App\Domains\Institucional\Domain\Models\ProfileSection;
use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Support\Exceptions\ResourceNotAvailable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Grava uma seção do perfil institucional. Seção editorial altera só o valor de trabalho: o publicado espera a
 * publicação explícita. Seção operacional grava os dois pares na mesma transação e vale ao salvar (DA2-16).
 * Voltar a herdar sem linha anterior não grava nada, porque ausência equivale a `inherit`.
 */
final readonly class SaveProfileSectionAction
{
    public function __construct(
        private ProfileResolver $resolver,
        private AuditContext $audit,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ResourceNotAvailable
     */
    public function execute(SaveProfileSectionData $data, ProfileOwner $owner): ?ProfileSection
    {
        return AuditLog::transaction(function () use ($data, $owner): ?ProfileSection {
            Gate::authorize('account.institutional.update', $owner->node());

            $company = Company::where('account_id', $owner->accountId)
                ->whereKey($owner->companyId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $company->active) {
                throw new ResourceNotAvailable;
            }

            if ($owner->isEstablishment()) {
                $establishment = Establishment::where('account_id', $owner->accountId)
                    ->where('company_id', $owner->companyId)
                    ->lockForUpdate()
                    ->findOrFail($owner->establishmentId);

                if (! $establishment->active) {
                    throw new ResourceNotAvailable;
                }
            }

            $row = ProfileSection::where('account_id', $owner->accountId)
                ->where('company_id', $owner->companyId)
                ->where('section', $data->section->value)
                ->when(
                    $owner->isEstablishment(),
                    fn ($query) => $query->where('establishment_id', $owner->establishmentId),
                    fn ($query) => $query->whereNull('establishment_id'),
                )
                ->lockForUpdate()
                ->first();

            $sectionDTO = new SaveProfileSectionDTO(mode: $data->mode, value: $data->value);

            if ($row === null && $data->mode === 'inherit') {
                return null;
            }

            if ($row === null) {
                $row = ProfileSection::register(
                    $sectionDTO,
                    $owner->accountId,
                    $owner->companyId,
                    $owner->establishmentId,
                    $data->section->value,
                );
            } else {
                $row->revise($sectionDTO);
            }

            if ($data->section->isOperational()) {
                $userId = $this->audit->actorId ?? (int) auth()->id();
                $row->revisePublished($sectionDTO, $userId);
            }

            if (! $row->exists || $row->isDirty()) {
                $row->saveOrFail();
            }

            $this->resolver->forget($owner);

            return $row;
        });
    }
}
