<?php

declare(strict_types=1);

namespace App\Domains\Midia\Application\Queries;

use App\Domains\Midia\Application\Data\ListMediaData;
use App\Domains\Midia\Domain\Models\MediaAsset;
use App\Domains\Midia\Domain\ValueObjects\MediaOwner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * A biblioteca vista de uma empresa ou de uma unidade, sempre dentro da conta e da empresa. Na empresa: a corporativa e a
 * local de todas as unidades. Na unidade: a corporativa (só leitura) e a própria, nunca a de outra unidade. O escopo é
 * aplicado no SQL antes de paginar e contar; as variantes vêm numa única consulta adicional, sem N+1.
 */
final readonly class ListMediaQuery
{
    /** @return LengthAwarePaginator<int, MediaAsset> */
    public function execute(MediaOwner $context, ListMediaData $filters, string $path): LengthAwarePaginator
    {
        $query = MediaAsset::query()
            ->leftJoin('establishments', function ($join): void {
                $join->on('establishments.id', '=', 'media_assets.establishment_id')
                    ->on('establishments.company_id', '=', 'media_assets.company_id');
            })
            ->where('media_assets.account_id', $context->accountId)
            ->where('media_assets.company_id', $context->companyId)
            ->when($context->isEstablishment(), fn (Builder $scope) => $scope->where(
                fn (Builder $visible) => $visible->whereNull('media_assets.establishment_id')
                    ->orWhere('media_assets.establishment_id', $context->establishmentId),
            ))
            ->when($filters->purpose !== null, fn (Builder $scope) => $scope->where('media_assets.purpose', $filters->purpose?->value))
            ->when($filters->scope === 'corporate', fn (Builder $scope) => $scope->whereNull('media_assets.establishment_id'))
            ->when($filters->scope === 'local', fn (Builder $scope) => $scope->whereNotNull('media_assets.establishment_id'))
            ->when($filters->search !== '', fn (Builder $scope) => $scope->whereLike('media_assets.alt_text', '%'.$filters->search.'%'))
            ->select('media_assets.*')
            ->selectRaw('establishments.name as establishment_name')
            ->with('variants')
            ->orderByDesc('media_assets.created_at')
            ->orderByDesc('media_assets.id');

        return $query->paginate(ListMediaData::PAGE_SIZE)->withPath($path)->withQueryString();
    }
}
