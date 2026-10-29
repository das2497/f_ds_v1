<?php

namespace App\Services\Fulfillment;

use App\Mail\OrderShippedMail;
use App\Models\Order;
use App\Models\VendorOrder;
use App\Services\Vendor\CJService;
use App\Services\Vendor\PrintfulService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * 2-hour hold state machine (1_0.txt §4):
 *  - submitReadyOrders() is what the scheduled VendorSyncJob calls every
 *    run — it only touches orders whose release_at has passed, inside a
 *    row lock, so a concurrent cancellation can never slip through.
 *  - submitVendorOrder() never blind-retries: it checks the vendor for an
 *    existing order under our internal_reference first, and never
 *    re-submits a VendorOrder already marked "submitted".
 */
class FulfillmentEngine
{
    public function __construct(
        private readonly VendorRouter $router,
        private readonly PrintfulService $printful,
        private readonly CJService $cj,
    ) {}

    /**
     * Find every paid order whose hold has expired and route + submit it.
     * Safe to call repeatedly (e.g. every scheduler tick) — already-routed
     * orders are skipped via the vendor_orders existence check.
     */
    public function submitReadyOrders(): int
    {
        $submitted = 0;

        Order::where('status', 'paid')
            ->where('release_at', '<=', now())
            ->doesntHave('vendorOrders')
            ->orderBy('release_at')
            ->chunkById(20, function ($orders) use (&$submitted) {
                foreach ($orders as $order) {
                    if ($this->routeAndSubmit($order)) {
                        $submitted++;
                    }
                }
            });

        return $submitted;
    }

    /**
     * Routes one order's items into VendorOrders and submits each — locked
     * so a cancellation racing against this exact moment always wins.
     */
    public function routeAndSubmit(Order $order): bool
    {
        $vendorOrders = DB::transaction(function () use ($order) {
            $locked = Order::where('id', $order->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== 'paid' || $locked->cancelled_at) {
                return null;
            }

            if (! $locked->release_at || $locked->release_at->isFuture()) {
                return null;
            }

            return $this->router->routeOrder($locked);
        });

        if (! $vendorOrders) {
            return false;
        }

        foreach ($vendorOrders as $vendorOrder) {
            $this->submitVendorOrder($vendorOrder);
        }

        $this->notifyIfFullySubmitted($order);

        return true;
    }

    /**
     * Fires the "shipped" lifecycle email exactly once, the first time
     * every vendor order for this order has been accepted — safe to call
     * after any retry (including from the admin Force Retry action), since
     * the DB-guarded update only lets one caller ever win the send.
     */
    public function notifyIfFullySubmitted(Order $order): void
    {
        $order->loadMissing('vendorOrders');

        $fullySubmitted = $order->vendorOrders->isNotEmpty()
            && $order->vendorOrders->every(fn ($vo) => $vo->status === 'submitted');

        if (! $fullySubmitted) {
            return;
        }

        $won = Order::where('id', $order->id)->whereNull('shipped_notified_at')->update(['shipped_notified_at' => now()]);

        if ($won) {
            Mail::to($order->email)->queue(new OrderShippedMail($order));
        }
    }

    public function submitVendorOrder(VendorOrder $vendorOrder): void
    {
        // Never re-submit — this is the duplicate-order guard.
        if ($vendorOrder->status === 'submitted') {
            return;
        }

        $service = $vendorOrder->vendor === 'printful' ? $this->printful : $this->cj;
        $attemptNumber = $vendorOrder->attempts()->count() + 1;

        // A variant can go out of stock any time during the 2-hour hold.
        // Caught here, before ever calling the vendor API, so the failure
        // reason is unambiguous rather than whatever the vendor happens to
        // return — surfaces in the admin Retry Console for manual refund.
        $outOfStockItem = $vendorOrder->items()->with('orderItem.variant')->get()
            ->first(fn ($item) => $item->orderItem->variant && $item->orderItem->variant->stock_status === 'out_of_stock');

        if ($outOfStockItem) {
            $vendorOrder->attempts()->create([
                'attempt_number' => $attemptNumber,
                'status' => 'failed',
                'error_message' => "\"{$outOfStockItem->orderItem->product_title}\" ({$outOfStockItem->orderItem->sku}) is now out of stock — needs manual refund or substitution.",
            ]);

            $vendorOrder->update(['status' => 'failed']);

            return;
        }

        if (! $service->configured()) {
            $vendorOrder->attempts()->create([
                'attempt_number' => $attemptNumber,
                'status' => 'skipped',
                'error_message' => ucfirst($vendorOrder->vendor).' API credentials are not configured yet.',
            ]);

            return;
        }

        try {
            // Idempotent re-check: a prior attempt may have timed out after
            // the vendor actually accepted the order, so check by our own
            // internal_reference before ever creating a new one.
            $existing = $service->findOrderByExternalId($vendorOrder->internal_reference);

            $result = $existing ?? $service->createOrder(
                $vendorOrder->internal_reference,
                $this->vendorItemsPayload($vendorOrder),
                $this->shippingAddressPayload($vendorOrder->order),
            );

            $vendorOrder->update([
                'vendor_external_order_id' => (string) ($result['id'] ?? $result['external_id'] ?? ''),
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            $vendorOrder->attempts()->create([
                'attempt_number' => $attemptNumber,
                'status' => $existing ? 'recovered' : 'success',
                'response_payload' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Vendor order submission failed', [
                'vendor_order_id' => $vendorOrder->id,
                'vendor' => $vendorOrder->vendor,
                'message' => $e->getMessage(),
            ]);

            $vendorOrder->attempts()->create([
                'attempt_number' => $attemptNumber,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $vendorOrder->update(['status' => 'failed']);
        }
    }

    /**
     * @return array<int, array{vendor_sku: string, quantity: int}>
     */
    private function vendorItemsPayload(VendorOrder $vendorOrder): array
    {
        return $vendorOrder->items()
            ->with('orderItem')
            ->get()
            ->map(fn ($item) => [
                'vendor_sku' => $item->orderItem->sku,
                'quantity' => $item->quantity,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function shippingAddressPayload(Order $order): array
    {
        return [
            'name' => $order->shipping_name,
            'address1' => $order->shipping_address_line1,
            'address2' => $order->shipping_address_line2,
            'city' => $order->shipping_city,
            'state_code' => $order->shipping_state,
            'zip' => $order->shipping_zip,
            'country_code' => $order->shipping_country,
        ];
    }
}
