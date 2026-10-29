<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'vendor', 'vendor_external_id', 'raw_payload', 'title', 'base_cost',
    'preview_image_url', 'category_hint', 'variant_count', 'status',
    'selected_by', 'selected_at',
])]
class VendorProductCandidate extends Model
{
    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'base_cost' => 'decimal:2',
            'selected_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function selectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by');
    }
}
