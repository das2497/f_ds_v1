<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\Request;

#[Fillable([
    'order_number', 'user_id', 'guest_token', 'email', 'phone',
    'shipping_name', 'shipping_address_line1', 'shipping_address_line2',
    'shipping_city', 'shipping_state', 'shipping_zip', 'shipping_country',
    'shipping_method', 'subtotal', 'shipping_total', 'tax_total', 'total',
    'currency', 'status', 'vendor_split_tax_breakdown', 'release_at', 'cancelled_at',
    'session_id',
    'first_touch_source', 'first_touch_medium', 'first_touch_campaign', 'first_touch_ad_id',
    'last_touch_source', 'last_touch_medium', 'last_touch_campaign', 'last_touch_ad_id',
    'fbp', 'fbc', 'ttclid',
])]
class Order extends Model
{
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'vendor_split_tax_breakdown' => 'array',
            'release_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasOne<Payment, $this>
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * @return HasMany<VendorOrder, $this>
     */
    public function vendorOrders(): HasMany
    {
        return $this->hasMany(VendorOrder::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function canStillBeCancelled(): bool
    {
        return $this->status === 'paid'
            && $this->release_at
            && $this->release_at->isFuture()
            && ! $this->vendorOrders()->where('status', 'submitted')->exists();
    }

    public function totalSupplierCost(): float
    {
        return (float) $this->items->sum(fn ($item) => $item->unit_cost * $item->quantity);
    }

    public function totalRefunded(): float
    {
        return (float) $this->refunds()->where('status', 'succeeded')->sum('amount');
    }

    /**
     * @return array{label: string, class: string}
     */
    public function displayStatus(): array
    {
        return match (true) {
            $this->status === 'cancelled' => ['label' => 'Cancelled', 'class' => 'badge-status-hold'],
            $this->status === 'refunded' => ['label' => 'Refunded', 'class' => 'badge-status-refunded'],
            $this->status === 'pending_payment' => ['label' => 'Pending Payment', 'class' => 'badge-status-hold'],
            $this->status === 'paid' && $this->release_at?->isFuture() => ['label' => 'Hold', 'class' => 'badge-status-hold'],
            $this->status === 'paid' && $this->vendorOrders->isEmpty() => ['label' => 'Processing', 'class' => 'badge-status-processing'],
            $this->status === 'paid' && $this->vendorOrders->contains('status', 'failed') => ['label' => 'Failed Retry', 'class' => 'badge-status-failed'],
            $this->status === 'paid' && $this->vendorOrders->every(fn ($vo) => $vo->status === 'submitted') => ['label' => 'Vendor Submitted', 'class' => 'badge-status-submitted'],
            default => ['label' => 'Processing', 'class' => 'badge-status-processing'],
        };
    }

    public function isViewableBy(Request $request): bool
    {
        if ($this->user_id) {
            return $request->user()?->id === $this->user_id;
        }

        return $this->guest_token && $this->guest_token === $request->cookie('cart_token');
    }
}
