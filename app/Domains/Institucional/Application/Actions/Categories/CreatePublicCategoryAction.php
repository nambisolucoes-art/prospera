<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Actions\Categories;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Institucional\Application\Data\Categories\CreatePublicCategoryData;
use App\Domains\Institucional\Domain\DTOs\Categories\CreatePublicCategoryDTO;
use App\Domains\Institucional\Domain\Models\PublicCategory;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Support\Exceptions\ResourceNotAvailable;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Cadastra uma categoria pública da conta. Ainda não há tela nem permissão: a administração chega com o diretório
 * (S13), que define a permissão de tenant. Cada categoria pertence a uma única conta e a trava da conta serializa
 * os cadastros que disputam nome e slug.
 */
final readonly class CreatePublicCategoryAction
{
    public function __construct(private AccountContext $context) {}

    /**
     * Enquanto a permissão de tenant não existe, só quem está dentro da própria conta cria categorias, e os dados
     * validados precisam ser da mesma conta. Nenhuma rota nem tela chama esta Action.
     *
     * @throws AuthorizationException
     * @throws ResourceNotAvailable
     */
    public function execute(CreatePublicCategoryData $data, Client $account): PublicCategory
    {
        if ($this->context->linkedId(auth()->user()) !== $account->id) {
            throw new AuthorizationException;
        }

        if ($data->accountId !== $account->id) {
            throw new ResourceNotAvailable;
        }

        return AuditLog::transaction(function () use ($data, $account): PublicCategory {
            Client::whereKey($account->id)->lockForUpdate()->firstOrFail();

            $categoryDTO = new CreatePublicCategoryDTO(
                name: $data->name,
                normalizedName: $data->normalizedName,
                slug: $data->slug,
                sortOrder: $data->sortOrder,
                seoTitle: $data->seoTitle,
                seoDescription: $data->seoDescription,
            );

            $category = PublicCategory::register($categoryDTO, $account->id);
            $category->saveOrFail();

            return $category;
        });
    }
}
