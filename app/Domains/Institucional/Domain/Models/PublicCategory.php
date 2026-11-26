<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Domain\Models;

use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use App\Domains\Institucional\Domain\DTOs\Categories\CreatePublicCategoryDTO;
use App\Domains\Institucional\Domain\DTOs\Categories\UpdatePublicCategoryDTO;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Categoria pública da conta (PD2-12), independente das etiquetas de Pessoas, Clientes, Fornecedores e
 * Colaboradores. Tem slug, ordem e conteúdo de SEO. Não há exclusão: categoria deixa de ser usada ao inativar.
 *
 * @property int $id
 * @property int $account_id
 * @property string $name
 * @property string $normalized_name
 * @property string $slug
 * @property int $sort_order
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property bool $active
 */
#[Fillable([
    'account_id', 'name', 'normalized_name', 'slug', 'sort_order', 'seo_title', 'seo_description', 'active',
])]
class PublicCategory extends Model implements Auditable
{
    use AuditsChanges;

    protected $table = 'public_categories';

    protected $attributes = ['active' => true, 'sort_order' => 0];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'sort_order' => 'integer'];
    }

    public static function register(CreatePublicCategoryDTO $data, int $accountId): self
    {
        return new self([
            'account_id' => $accountId,
            'name' => $data->name,
            'normalized_name' => $data->normalizedName,
            'slug' => $data->slug,
            'sort_order' => $data->sortOrder,
            'seo_title' => $data->seoTitle,
            'seo_description' => $data->seoDescription,
            'active' => true,
        ]);
    }

    public function revise(UpdatePublicCategoryDTO $data): void
    {
        $this->fill([
            'name' => $data->name,
            'normalized_name' => $data->normalizedName,
            'slug' => $data->slug,
            'sort_order' => $data->sortOrder,
            'seo_title' => $data->seoTitle,
            'seo_description' => $data->seoDescription,
            'active' => $data->active,
        ]);
    }

    public static function auditType(): string
    {
        return 'public-category';
    }

    /** @return array<string,string> */
    public static function auditedFields(): array
    {
        return [
            'account_id' => 'Conta',
            'name' => 'Nome',
            'slug' => 'Endereço (slug)',
            'sort_order' => 'Ordem',
            'seo_title' => 'Título de SEO',
            'seo_description' => 'Descrição de SEO',
            'active' => 'Situação',
        ];
    }

    /** @return array<string,string> */
    public static function unauditedFields(): array
    {
        return ['normalized_name' => 'Derivado de name (NormalizedName), sem informação própria.'];
    }
}
