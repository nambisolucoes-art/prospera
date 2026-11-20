<?php

namespace App\Domains\Etiquetas\Application\Actions;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Etiquetas\Application\Data\SaveTagData;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Pessoas\Application\Actions\DirectoryAudit;
use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Support\Facades\Gate;

final class SaveTagAction
{
    public function execute(int $accountId, ?Tag $tag, SaveTagData $data): void
    {
        AuditLog::transaction(function () use ($accountId, $tag, $data) {
            Client::whereKey($accountId)->lockForUpdate()->firstOrFail();
            if ($tag) {
                $tag = Tag::where('account_id', $accountId)->lockForUpdate()->findOrFail($tag->id);
                abort_if($tag->system_key !== null, 403, 'Etiquetas especiais são gerenciadas pelo sistema.');
            }
            $tag ??= new Tag(['account_id' => $accountId]);
            $before = $tag->exists ? $tag->only(['name', 'module', 'color', 'active']) : null;
            $tag->fill($data->values);
            if (($tag->exists && $tag->isDirty('active')) || (! $tag->exists && ! $tag->active)) {
                if ($tag->active) {
                    Gate::authorize('account.tags.activate');
                } else {
                    Gate::authorize('account.tags.deactivate');
                }
            }
            if (! $tag->isDirty()) {
                return;
            } $tag->saveOrFail();
            DirectoryAudit::record($accountId, $before ? 'tag.updated' : 'tag.created', 'tag', $tag->id, ['before' => $before, 'after' => $tag->only(['name', 'module', 'color', 'active'])]);
        });
    }
}
