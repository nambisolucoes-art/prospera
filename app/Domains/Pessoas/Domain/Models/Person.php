<?php

namespace App\Domains\Pessoas\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'email'])]
class Person extends Model {}
