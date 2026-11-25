<?php

declare(strict_types=1);

namespace App\Domains\Etiquetas\Application\Actions\Tags;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Etiquetas\Application\Data\Tags\UpdateTagData;
use App\Domains\Etiquetas\Domain\DTOs\Tags\UpdateTagDTO;
use App\Domains\Etiquetas\Domain\Exceptions\SystemTagIsProtected;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Altera uma etiqueta comercial. Ativar e inativar têm permissão própria, conferida quando a situação muda. */
final readonly class UpdateTagAction
{
    /**
     * @throws AuthorizationException
     * @throws SystemTagIsProtected
     * @throws ValidationException
     */
    public function execute(UpdateTagData $data, Tag $tag): void
    {
        AuditLog::transaction(function () use ($data, $tag): void {
            Client::whereKey($tag->account_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedTag = Tag::where('account_id', $tag->account_id)
                ->lockForUpdate()
                ->findOrFail($tag->id);

            if ($lockedTag->system_key !== null) {
                throw new SystemTagIsProtected;
            }

            if ($lockedTag->module !== $data->module && $lockedTag->isAssigned()) {
                throw ValidationException::withMessages(['module' => 'Esta etiqueta está em uso no módulo atual.']);
            }

            $tagDTO = new UpdateTagDTO(
                name: $data->name,
                module: $data->module,
                color: $data->color,
                active: $data->active ?? $lockedTag->active,
            );

            $lockedTag->revise($tagDTO);

            if ($lockedTag->isDirty('active')) {
                if ($lockedTag->active) {
                    Gate::authorize('account.tags.activate');
                } else {
                    Gate::authorize('account.tags.deactivate');
                }
            }

            if ($lockedTag->isDirty()) {
                $lockedTag->saveOrFail();
            }
        });
    }
}
