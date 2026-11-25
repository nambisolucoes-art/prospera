<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Queries\Establishments;

use App\Domains\Organizacao\Domain\Models\Establishment;

final readonly class ShowEstablishmentQuery
{
    public function execute(int $accountId, int $id): Establishment
    {
        return Establishment::where('account_id', $accountId)
            ->with('company:id,account_id,name,trade_name,person_type,tax_root,individual_document,active')
            ->findOrFail($id);
    }
}
