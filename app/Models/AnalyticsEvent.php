<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'session_id', 'user_id', 'event_name', 'event_id',
    'utm_source', 'utm_medium', 'utm_campaign', 'utm_ad_id',
    'path', 'product_id', 'order_id', 'value', 'currency',
])]
class AnalyticsEvent extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'ga4_sent_at' => 'datetime',
            'meta_sent_at' => 'datetime',
            'tiktok_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
