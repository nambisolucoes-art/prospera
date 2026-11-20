<?php

namespace App\Domains\Auditoria\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property array<string,array{label:string,before:mixed,after:mixed}> $changes
 * @property Carbon $created_at
 */
class AuditRecord extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }
}
