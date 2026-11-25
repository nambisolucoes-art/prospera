<?php

declare(strict_types=1);

namespace App\Domains\Etiquetas\Application\Actions\Tags;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Etiquetas\Application\Data\Tags\CreateTagData;
use App\Domains\Etiquetas\Domain\DTOs\Tags\CreateTagDTO;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/** Cadastra uma etiqueta comercial da conta. Cadastrar já inativa exige também a permissão de inativar. */
final readonly class CreateTagAction
{
    /** @throws AuthorizationException */
    public function execute(CreateTagData $data, int $accountId): void
    {
        AuditLog::transaction(function () use ($data, $accountId): void {
            Client::whereKey($accountId)
                ->lockForUpdate()
                ->firstOrFail();

            // Cada slug fica literal na chamada para que permissions:sync o descubra.
            if (! $data->active) {
                Gate::authorize('account.tags.deactivate');
            }

            $tagDTO = new CreateTagDTO(
                name: $data->name,
                module: $data->module,
                color: $data->color,
                active: $data->active,
            );

            $tag = Tag::register($tagDTO, $accountId);
            $tag->saveOrFail();
        });
    }
}
