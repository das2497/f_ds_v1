<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['state_code', 'state_name', 'rate'])]
class TaxRate extends Model
{
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
        ];
    }
}
