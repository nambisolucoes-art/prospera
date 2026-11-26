<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Actions\Categories;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Institucional\Application\Data\Categories\UpdatePublicCategoryData;
use App\Domains\Institucional\Domain\DTOs\Categories\UpdatePublicCategoryDTO;
use App\Domains\Institucional\Domain\Models\PublicCategory;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Support\Exceptions\ResourceNotAvailable;
use Illuminate\Auth\Access\AuthorizationException;

/** Edita ou inativa uma categoria pública da conta. Não há exclusão. Atualização sem mudança não gera evento. */
final readonly class UpdatePublicCategoryAction
{
    public function __construct(private AccountContext $context) {}

    /**
     * A categoria é lida sempre dentro da conta (de outra conta responde como inexistente), e só quem está dentro da
     * própria conta a altera enquanto a permissão de tenant não existe.
     *
     * @throws AuthorizationException
     * @throws ResourceNotAvailable
     */
    public function execute(UpdatePublicCategoryData $data, int $categoryId, Client $account): PublicCategory
    {
        if ($this->context->linkedId(auth()->user()) !== $account->id) {
            throw new AuthorizationException;
        }

        if ($data->accountId !== $account->id) {
            throw new ResourceNotAvailable;
        }

        return AuditLog::transaction(function () use ($data, $categoryId, $account): PublicCategory {
            Client::whereKey($account->id)->lockForUpdate()->firstOrFail();

            $category = PublicCategory::where('account_id', $account->id)->lockForUpdate()->findOrFail($categoryId);

            $categoryDTO = new UpdatePublicCategoryDTO(
                name: $data->name,
                normalizedName: $data->normalizedName,
                slug: $data->slug,
                sortOrder: $data->sortOrder,
                seoTitle: $data->seoTitle,
                seoDescription: $data->seoDescription,
                active: $data->active,
            );

            $category->revise($categoryDTO);

            if ($category->isDirty()) {
                $category->saveOrFail();
            }

            return $category;
        });
    }
}
