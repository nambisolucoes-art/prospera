<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Actions\Profile;

use App\Domains\Auditoria\Application\Actions\AuditContext;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Institucional\Application\Context\ProfileResolver;
use App\Domains\Institucional\Application\Data\Profile\UpdateProfileSectionData;
use App\Domains\Institucional\Domain\DTOs\Profile\UpdateProfileSectionDTO;
use App\Domains\Institucional\Domain\Exceptions\InvalidProfileSection;
use App\Domains\Institucional\Domain\Models\ProfileSection;
use App\Support\Exceptions\ResourceNotAvailable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

/**
 * Altera uma seção que o dono já gravou. Seção editorial altera só o valor de trabalho: o publicado espera a publicação
 * explícita. Seção operacional grava os dois pares na mesma transação e vale ao salvar (DA2-16). Sem mudança, nada é
 * gravado nem auditado.
 */
final readonly class UpdateProfileSectionAction
{
    public function __construct(
        private LockProfileOwnerAction $lockOwner,
        private ProfileResolver $resolver,
        private AuditContext $audit,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws InvalidProfileSection
     * @throws ModelNotFoundException
     * @throws ResourceNotAvailable
     */
    public function execute(UpdateProfileSectionData $data, ProfileSection $section): ProfileSection
    {
        return AuditLog::transaction(function () use ($data, $section): ProfileSection {
            $owner = $section->owner();

            Gate::authorize('account.institutional.update', $owner->node());

            $this->lockOwner->execute($owner);

            $lockedSection = ProfileSection::where('account_id', $section->account_id)
                ->where('company_id', $section->company_id)
                ->lockForUpdate()
                ->findOrFail($section->id);

            $sectionDTO = new UpdateProfileSectionDTO(
                mode: $data->mode,
                value: $data->value,
            );

            $lockedSection->revise($sectionDTO);

            if ($data->section->isOperational()) {
                $userId = $this->audit->actorId ?? (int) auth()->id();
                $lockedSection->revisePublished($sectionDTO, $userId);
            }

            if ($lockedSection->isDirty()) {
                $lockedSection->saveOrFail();
            }

            $this->resolver->forget($owner);

            return $lockedSection;
        });
    }
}
