<?php

declare(strict_types=1);

namespace App\Domains\Etiquetas\Application\Actions\Tags;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Etiquetas\Domain\Exceptions\SystemTagIsProtected;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class DeleteTagAction
{
    /**
     * @throws AuthorizationException
     * @throws SystemTagIsProtected
     * @throws ValidationException
     */
    public function execute(int $accountId, int $id): void
    {
        Gate::authorize('account.tags.delete');

        AuditLog::transaction(function () use ($accountId, $id): void {
            Client::whereKey($accountId)
                ->lockForUpdate()
                ->firstOrFail();

            $tag = Tag::where('account_id', $accountId)
                ->lockForUpdate()
                ->findOrFail($id);

            if ($tag->system_key !== null) {
                throw new SystemTagIsProtected;
            }

            if ($tag->isAssigned()) {
                throw ValidationException::withMessages([
                    '_form' => 'Esta etiqueta possui registros vinculados. Inative a etiqueta em vez de excluir.',
                ]);
            }

            $tag->delete();
        });
    }
}
