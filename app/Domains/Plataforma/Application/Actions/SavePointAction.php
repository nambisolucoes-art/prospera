<?php

namespace App\Domains\Plataforma\Application\Actions;

use App\Domains\Plataforma\Application\Data\SavePointData;
use App\Domains\Plataforma\Domain\DTOs\PointConfigurationDTO;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Plataforma\Domain\Models\Point;

final class SavePointAction
{
    public function execute(Client $account, ?Point $point, SavePointData $data): void
    {
        $point ??= $account->points()->make();
        $point->configure(new PointConfigurationDTO($data->name, $data->active));
        $point->save();
    }
}
