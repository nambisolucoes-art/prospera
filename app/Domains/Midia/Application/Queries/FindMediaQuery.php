<?php

declare(strict_types=1);

namespace App\Domains\Midia\Application\Queries;

use App\Domains\Midia\Domain\Models\MediaAsset;
use App\Domains\Midia\Domain\ValueObjects\MediaOwner;

/** Uma mídia visível no contexto (mesma regra de escopo da listagem), com as variantes. Outro escopo não existe. */
final readonly class FindMediaQuery
{
    public function execute(MediaOwner $context, int $id): ?MediaAsset
    {
        return MediaAsset::where('account_id', $context->accountId)
            ->where('company_id', $context->companyId)
            ->when($context->isEstablishment(), fn ($scope) => $scope->where(
                fn ($visible) => $visible->whereNull('establishment_id')->orWhere('establishment_id', $context->establishmentId),
            ))
            ->with('variants')
            ->whereKey($id)
            ->first();
    }
}
