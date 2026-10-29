<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['order_id', 'vendor', 'internal_reference', 'vendor_external_order_id', 'status', 'submitted_at'])]
class VendorOrder extends Model
{
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return HasMany<VendorOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(VendorOrderItem::class);
    }

    /**
     * @return HasMany<FulfillmentAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(FulfillmentAttempt::class);
    }
}
