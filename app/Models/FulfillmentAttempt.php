<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['vendor_order_id', 'attempt_number', 'status', 'response_payload', 'error_message'])]
class FulfillmentAttempt extends Model
{
    protected function casts(): array
    {
        return [
            'response_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<VendorOrder, $this>
     */
    public function vendorOrder(): BelongsTo
    {
        return $this->belongsTo(VendorOrder::class);
    }
}
