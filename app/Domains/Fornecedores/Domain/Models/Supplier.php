<?php

namespace App\Domains\Fornecedores\Domain\Models;

use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use App\Domains\Pessoas\Domain\Models\Person;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['account_id', 'person_id', 'active'])]
class Supplier extends Model implements Auditable
{
    use AuditsChanges;

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function getNameAttribute(): ?string
    {
        return Person::where('account_id', $this->account_id)->whereKey($this->person_id)->value('name');
    }

    public static function auditType(): string
    {
        return 'supplier';
    }

    /** @return array<string,string> */
    public static function auditedFields(): array
    {
        return ['account_id' => 'Conta', 'person_id' => 'Pessoa', 'active' => 'Situação'];
    }
}
