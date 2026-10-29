<?php

namespace App\Services\Fulfillment;

use App\Models\Order;
use App\Models\VendorOrder;
use Illuminate\Support\Collection;

/**
 * Splits a paid order's items by source vendor into one VendorOrder per
 * vendor present. Keyed by a unique internal_reference so calling this
 * twice for the same order never creates duplicate VendorOrder rows.
 */
class VendorRouter
{
    /**
     * @return Collection<int, VendorOrder>
     */
    public function routeOrder(Order $order): Collection
    {
        $order->loadMissing('items');

        return $order->items
            ->groupBy('source_vendor')
            ->reject(fn ($items, $vendor) => blank($vendor))
            ->map(function ($items, $vendor) use ($order) {
                $vendorOrder = VendorOrder::firstOrCreate(
                    ['internal_reference' => "{$order->order_number}-{$vendor}"],
                    ['order_id' => $order->id, 'vendor' => $vendor, 'status' => 'pending']
                );

                foreach ($items as $item) {
                    $vendorOrder->items()->firstOrCreate(
                        ['order_item_id' => $item->id],
                        ['quantity' => $item->quantity]
                    );
                }

                return $vendorOrder;
            })
            ->values();
    }
}
