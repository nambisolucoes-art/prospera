<?php

namespace App\Domains\Plataforma\Domain\Models;

use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use App\Domains\Plataforma\Domain\DTOs\PointConfigurationDTO;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'active'])]
class Point extends Model implements Auditable
{
    use AuditsChanges;

    public static function auditType(): string
    {
        return 'point';
    }

    /** @return array<string,string> */
    public static function auditedFields(): array
    {
        return ['name' => 'Nome', 'active' => 'Situação', 'client_id' => 'Conta'];
    }

    /** @return array<string,string> */
    public static function unauditedFields(): array
    {
        return ['establishment_id' => 'Vínculo legado, substituído por client_id.'];
    }

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
