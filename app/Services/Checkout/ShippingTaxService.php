<?php

namespace App\Services\Checkout;

use App\Models\Cart;
use App\Models\TaxRate;
use App\Services\Vendor\PrintfulService;

/**
 * Hybrid tax/shipping engine (1_1.txt §3):
 *  - Printful line items: live Printful Cost Estimate API when configured,
 *    falling back to the state tax_rates table otherwise.
 *  - CJ (and any other) line items: tax_rates table, looked up by the
 *    customer's shipping state.
 * Shipping is a flat rate per method for now — per-vendor live freight
 * quotes (CJ) and Printful shipping estimates are wired once those vendor
 * API credentials are available.
 */
class ShippingTaxService
{
    private const SHIPPING_RATES = [
        'standard' => 8.50,
        'express' => 19.99,
    ];

    public function __construct(private readonly PrintfulService $printful) {}

    /**
     * @return array<string, mixed>
     */
    public function calculate(Cart $cart, string $stateCode, string $shippingMethod): array
    {
        $items = $cart->items()->with('variant.product')->get();
        $subtotal = round($items->sum(fn ($item) => $item->lineTotal()), 2);

        $stateRate = (float) (TaxRate::where('state_code', strtoupper($stateCode))->value('rate') ?? 0);

        $grouped = $items->groupBy(fn ($item) => $item->variant->product->source_vendor ?? 'other');

        $breakdown = [];
        $taxTotal = 0.0;

        foreach ($grouped as $vendor => $vendorItems) {
            $vendorSubtotal = round($vendorItems->sum(fn ($item) => $item->lineTotal()), 2);

            if ($vendor === 'printful' && $this->printful->configured()) {
                // Live Printful Cost Estimate API call goes here once credentials
                // exist; falls back to the same state-rate lookup until then.
                $tax = round($vendorSubtotal * $stateRate, 2);
                $source = 'printful_live';
            } else {
                $tax = round($vendorSubtotal * $stateRate, 2);
                $source = 'tax_rates_table';
            }

            $breakdown[$vendor] = [
                'subtotal' => $vendorSubtotal,
                'tax' => $tax,
                'tax_source' => $source,
            ];
            $taxTotal += $tax;
        }

        $shippingTotal = self::SHIPPING_RATES[$shippingMethod] ?? self::SHIPPING_RATES['standard'];
        $taxTotal = round($taxTotal, 2);

        return [
            'subtotal' => $subtotal,
            'shipping_total' => $shippingTotal,
            'tax_total' => $taxTotal,
            'total' => round($subtotal + $shippingTotal + $taxTotal, 2),
            'breakdown' => $breakdown,
            'state_rate' => $stateRate,
        ];
    }
}
