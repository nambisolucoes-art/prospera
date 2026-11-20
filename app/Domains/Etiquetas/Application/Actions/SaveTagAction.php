<?php

namespace App\Domains\Etiquetas\Application\Actions;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Etiquetas\Application\Data\SaveTagData;
use App\Domains\Etiquetas\Domain\DTOs\TagDTO;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Support\Facades\Gate;

final class SaveTagAction
{
    public function execute(int $accountId, ?Tag $tag, SaveTagData $data): void
    {
        AuditLog::transaction(function () use ($accountId, $tag, $data): void {
            Client::whereKey($accountId)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedTag = $tag === null
                ? null
                : Tag::where('account_id', $accountId)
                    ->lockForUpdate()
                    ->findOrFail($tag->id);

            abort_if(
                $lockedTag?->system_key !== null,
                403,
                'Etiquetas especiais são gerenciadas pelo sistema.',
            );

            $tagDTO = new TagDTO(
                name: $data->values['name'],
                module: $data->values['module'],
                color: $data->values['color'],
                active: (bool) ($data->values['active'] ?? $lockedTag->active ?? true),
            );

            if ($lockedTag === null) {
                $lockedTag = Tag::register($tagDTO, $accountId);
            } else {
                $lockedTag->revise($tagDTO);
            }

            // Cada slug fica literal na chamada para que permissions:sync o descubra.
            if (
                ($lockedTag->exists && $lockedTag->isDirty('active'))
                || (! $lockedTag->exists && ! $lockedTag->active)
            ) {
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
