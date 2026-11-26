<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Actions\Profile;

use App\Domains\Auditoria\Application\Actions\AuditContext;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Institucional\Application\Context\ProfileResolver;
use App\Domains\Institucional\Application\Data\Profile\PublishProfileSectionsData;
use App\Domains\Institucional\Application\Queries\Profile\ProfileImpactQuery;
use App\Domains\Institucional\Domain\Models\ProfileSection;
use App\Domains\Institucional\Domain\ValueObjects\ProfileImpact;
use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Support\Exceptions\ResourceNotAvailable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Publica as seções editoriais escolhidas de um dono: copia o valor de trabalho para o publicado. O impacto é
 * recalculado dentro da transação e a confirmação do cliente precisa coincidir. Quem herda passa a ver o novo valor
 * publicado e quem tem `override` ou desativação não é tocado, nem em rascunho. O evento fica no nó escrito, com o
 * mesmo `operation_id` em todas as seções; as unidades que herdam são atingidas por leitura, sem evento próprio.
 */
final readonly class PublishProfileSectionsAction
{
    public function __construct(
        private ProfileImpactQuery $impact,
        private ProfileResolver $resolver,
        private AuditContext $audit,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ResourceNotAvailable
     * @throws ValidationException
     */
    public function execute(PublishProfileSectionsData $data, ProfileOwner $owner): ProfileImpact
    {
        return AuditLog::transaction(function () use ($data, $owner): ProfileImpact {
            Gate::authorize('account.institutional.publish', $owner->node());

            $company = Company::where('account_id', $owner->accountId)
                ->whereKey($owner->companyId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $company->active) {
                throw new ResourceNotAvailable;
            }

            $impact = $this->impact->execute($owner, $data->sections);

            if ($impact->changed === []) {
                return $impact;
            }

            if ($impact->inheriting !== $data->inheriting || $impact->overriding !== $data->overriding) {
                throw ValidationException::withMessages([
                    'impact' => 'O impacto mudou desde que você abriu o resumo. Revise os números e confirme de novo.',
                ]);
            }

            $userId = $this->audit->actorId ?? (int) auth()->id();

            foreach ($impact->changed as $type) {
                $row = ProfileSection::where('account_id', $owner->accountId)
                    ->where('company_id', $owner->companyId)
                    ->where('section', $type->value)
                    ->when(
                        $owner->isEstablishment(),
                        fn ($query) => $query->where('establishment_id', $owner->establishmentId),
                        fn ($query) => $query->whereNull('establishment_id'),
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $beforeMode = $row->published_mode;
                $beforeValue = $row->published_value;

                $row->publish($userId);
                $row->saveQuietly();

                AuditLog::record(
                    ProfileSection::auditType(),
                    $row->id,
                    $row->account_id,
                    'published',
                    (string) $row->getAttribute('name'),
                    [
                        'published_mode' => ['label' => 'Modo publicado', 'before' => $beforeMode, 'after' => $row->published_mode],
                        'published_value' => ['label' => 'Conteúdo publicado', 'before' => $beforeValue, 'after' => $row->published_value],
                        'impact_inheriting' => ['label' => 'Unidades que herdam', 'before' => null, 'after' => $impact->inheriting],
                        'impact_overriding' => ['label' => 'Unidades com conteúdo próprio', 'before' => null, 'after' => $impact->overriding],
                    ],
                    $row->company_id,
                    $row->establishment_id,
                );
            }

            $this->resolver->forget($owner);

            return $impact;
        });
    }
}
