<?php

declare(strict_types=1);

namespace App\Domains\Midia\Application\Actions;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Midia\Application\Data\UpdateMediaData;
use App\Domains\Midia\Domain\DTOs\Media\UpdateMediaAssetDTO;
use App\Domains\Midia\Domain\Models\MediaAsset;
use App\Support\Exceptions\ResourceNotAvailable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

/** Altera o texto alternativo e a licença. A autoridade é a do dono da mídia: a unidade não edita a mídia corporativa. */
final readonly class UpdateMediaAction
{
    public function __construct(
        private LockMediaOwnerAction $lockOwner,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     * @throws ResourceNotAvailable
     */
    public function execute(UpdateMediaData $data, MediaAsset $media): MediaAsset
    {
        return AuditLog::transaction(function () use ($data, $media): MediaAsset {
            $owner = $media->owner();

            Gate::authorize('account.media.update', $owner->node());

            $this->lockOwner->execute($owner);

            $lockedMedia = MediaAsset::where('account_id', $media->account_id)
                ->where('company_id', $media->company_id)
                ->lockForUpdate()
                ->findOrFail($media->id);

            $mediaDTO = new UpdateMediaAssetDTO(
                altText: $data->altText,
                licenseNote: $data->licenseNote,
            );

            $lockedMedia->revise($mediaDTO);

            if ($lockedMedia->isDirty()) {
                $lockedMedia->saveOrFail();
            }

            return $lockedMedia;
        });
    }
}
