<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'vendor', 'vendor_external_id', 'product_id', 'score', 'verdict', 'reasons',
    'risks', 'suggested_price', 'input_hash', 'scored_at',
])]
class AiProductScore extends Model
{
    protected function casts(): array
    {
        return [
            'reasons' => 'array',
            'risks' => 'array',
            'suggested_price' => 'decimal:2',
            'scored_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
