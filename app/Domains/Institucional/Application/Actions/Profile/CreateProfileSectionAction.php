<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Actions\Profile;

use App\Domains\Auditoria\Application\Actions\AuditContext;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Institucional\Application\Context\ProfileResolver;
use App\Domains\Institucional\Application\Data\Profile\CreateProfileSectionData;
use App\Domains\Institucional\Domain\DTOs\Profile\CreateProfileSectionDTO;
use App\Domains\Institucional\Domain\Exceptions\InvalidProfileSection;
use App\Domains\Institucional\Domain\Models\ProfileSection;
use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;
use App\Support\Exceptions\ConflictingChange;
use App\Support\Exceptions\ResourceNotAvailable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Grava pela primeira vez uma seção do perfil de uma empresa ou de um estabelecimento. Seção editorial grava só o valor
 * de trabalho: o publicado espera a publicação explícita. Seção operacional grava os dois pares na mesma transação e
 * vale ao salvar (DA2-16). Herdar sem linha anterior não grava nada, porque a ausência equivale a `inherit`.
 */
final readonly class CreateProfileSectionAction
{
    public function __construct(
        private LockProfileOwnerAction $lockOwner,
        private ProfileResolver $resolver,
        private AuditContext $audit,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ConflictingChange
     * @throws InvalidProfileSection
     * @throws ResourceNotAvailable
     */
    public function execute(CreateProfileSectionData $data, ProfileOwner $owner): ?ProfileSection
    {
        return AuditLog::transaction(function () use ($data, $owner): ?ProfileSection {
            Gate::authorize('account.institutional.update', $owner->node());

            $this->lockOwner->execute($owner);

            $sectionDTO = new CreateProfileSectionDTO(
                mode: $data->mode,
                value: $data->value,
            );

            $section = ProfileSection::register(
                $sectionDTO,
                $owner->accountId,
                $owner->companyId,
                $owner->establishmentId,
                $data->section->value,
            );

            // Outra pessoa gravou a primeira versão enquanto esta tela estava aberta: a edição vira alteração.
            $alreadySaved = ProfileSection::where('account_id', $owner->accountId)
                ->where('company_id', $owner->companyId)
                ->where('section', $data->section->value)
                ->when(
                    $owner->isEstablishment(),
                    fn ($query) => $query->where('establishment_id', $owner->establishmentId),
                    fn ($query) => $query->whereNull('establishment_id'),
                )
                ->exists();

            if ($alreadySaved) {
                throw new ConflictingChange('Esta seção acabou de ser gravada por outra pessoa. Recarregue e tente de novo.');
            }

            if ($data->mode === 'inherit') {
                return null;
            }

            if ($data->section->isOperational()) {
                $userId = $this->audit->actorId ?? (int) auth()->id();
                $section->revisePublished($sectionDTO, $userId);
            }

            $section->saveOrFail();

            $this->resolver->forget($owner);

            return $section;
        });
    }
}
