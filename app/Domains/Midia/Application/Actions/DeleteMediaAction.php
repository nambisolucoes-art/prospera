<?php

declare(strict_types=1);

namespace App\Domains\Midia\Application\Actions;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Midia\Application\Queries\MediaIsInUseQuery;
use App\Domains\Midia\Domain\Models\MediaAsset;
use App\Support\Exceptions\ResourceNotAvailable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Exclusão conservadora. Mídia sem uso é removida de vez. Mídia em uso nunca é apagada em silêncio: fica com a situação
 * "exclusão solicitada" (auditada) e sai quando deixar de ser usada, por evento ou pelo `media:purge`. Devolve `true`
 * quando a mídia foi removida e `false` quando ficou marcada.
 */
final readonly class DeleteMediaAction
{
    public function __construct(
        private LockMediaOwnerAction $lockOwner,
        private MediaIsInUseQuery $isInUse,
        private RemoveUnusedMediaAction $removeUnused,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ResourceNotAvailable
     */
    public function execute(MediaAsset $media): bool
    {
        $owner = $media->owner();

        Gate::authorize('account.media.delete', $owner->node());

        $marked = AuditLog::transaction(function () use ($media, $owner): bool {
            $this->lockOwner->execute($owner);

            $lockedMedia = MediaAsset::where('account_id', $media->account_id)
                ->where('company_id', $media->company_id)
                ->lockForUpdate()
                ->findOrFail($media->id);

            if (! $this->isInUse->execute($lockedMedia)) {
                return false;
            }

            $lockedMedia->requestRemoval();

            if ($lockedMedia->isDirty()) {
                $lockedMedia->saveOrFail();
            }

            return true;
        });

        if ($marked) {
            return false;
        }

        $this->removeUnused->execute($media);

        return true;
    }
}
