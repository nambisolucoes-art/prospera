<?php

namespace App\Domains\Etiquetas\Application\Actions;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class DeleteTagAction
{
    public function execute(int $accountId, int $id): void
    {
        Gate::authorize('account.tags.delete');
        AuditLog::transaction(function () use ($accountId, $id) {
            Client::whereKey($accountId)
                ->lockForUpdate()
                ->firstOrFail();

            $tag = Tag::where('account_id', $accountId)
                ->lockForUpdate()
                ->findOrFail($id);

            abort_if($tag->system_key !== null, 403, 'Etiquetas especiais são gerenciadas pelo sistema.');

            if ($tag->people()->exists()) {
                throw ValidationException::withMessages([
                    '_form' => 'Esta etiqueta possui registros vinculados. Inative a etiqueta em vez de excluir.',
                ]);
            }

            $tag->delete();
        });
    }
}
