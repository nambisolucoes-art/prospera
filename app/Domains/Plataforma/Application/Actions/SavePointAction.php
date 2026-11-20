<?php

namespace App\Domains\Plataforma\Application\Actions;

use App\Domains\Plataforma\Application\Data\SavePointData;
use App\Domains\Plataforma\Domain\DTOs\PointDTO;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Plataforma\Domain\Models\Point;

final class SavePointAction
{
    public function execute(Client $account, ?Point $point, SavePointData $data): void
    {
        $point ??= $account->points()->make();

        $pointDTO = new PointDTO(
            name: $data->name,
            active: $data->active,
        );

        $point->revise($pointDTO);
        $point->save();
    }
}
