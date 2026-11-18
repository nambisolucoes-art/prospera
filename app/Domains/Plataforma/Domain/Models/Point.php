<?php

namespace App\Domains\Plataforma\Domain\Models;

use App\Domains\Plataforma\Domain\DTOs\PointConfigurationDTO;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'active'])]
class Point extends Model
{
    public function configure(PointConfigurationDTO $configuration): void
    {
        $this->fill(['name' => $configuration->name, 'active' => $configuration->active]);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
