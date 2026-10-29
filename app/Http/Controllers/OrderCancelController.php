<?php

namespace App\Http\Controllers;

use App\Exceptions\VendorApiException;
use App\Mail\OrderCancelledMail;
use App\Models\Order;
use App\Services\Analytics\EventTracker;
use App\Services\Payment\StripeRefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class OrderCancelController extends Controller
{
    public function __construct(
        private readonly StripeRefundService $refunds,
        private readonly EventTracker $tracker,
    ) {}

    public function show(Request $request, Order $order): View
    {
        abort_unless($order->isViewableBy($request), 403);
        abort_unless($order->canStillBeCancelled(), 422, 'This order can no longer be cancelled online — the 2-hour window has passed.');

        return view('store.order-cancel', compact('order'));
    }

    public function destroy(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->isViewableBy($request), 403);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        // Race-safe: locked so a FulfillmentEngine run that grabs this same
        // order a moment later sees status=cancelled and backs off.
        $cancelled = DB::transaction(function () use ($order) {
            $locked = Order::where('id', $order->id)->lockForUpdate()->first();

            if (! $locked->canStillBeCancelled()) {
                return false;
            }

            $locked->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            return true;
        });

        abort_unless($cancelled, 422, 'This order can no longer be cancelled — it may already be with the vendor.');

        // The order is already cancelled above regardless of what happens
        // next — we never want FulfillmentEngine to ship it — but a failed
        // or errored refund must not crash the request or tell the
        // customer they were refunded when they weren't.
        try {
            $refund = $this->refunds->refund($order, (float) $order->total, $validated['reason'] ?? 'Customer cancellation');
        } catch (VendorApiException $e) {
            Log::error('Refund failed during order cancellation — needs manual processing.', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('order-success', $order)
                ->with('status', 'Your order has been cancelled. We hit a problem issuing the automatic refund — our team has been notified and will process it manually, with a confirmation email to follow.');
        }

        if ($refund->status !== 'succeeded') {
            Log::error('Refund did not succeed during order cancellation — needs manual processing.', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'refund_id' => $refund->id,
            ]);

            return redirect()->route('order-success', $order)
                ->with('status', 'Your order has been cancelled. We hit a problem issuing the automatic refund — our team has been notified and will process it manually, with a confirmation email to follow.');
        }

        Mail::to($order->email)->queue(new OrderCancelledMail($order, (float) $order->total, fullCancellation: true));

        $this->tracker->trackRefund($order, (float) $order->total);

        return redirect()->route('order-success', $order)->with('status', 'Order cancelled and refunded.');
    }
}
