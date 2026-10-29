<?php

namespace App\Services\Payment;

use App\Exceptions\VendorApiException;
use App\Models\Order;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;

class StripeRefundService
{
    public function __construct(private readonly StripePaymentService $stripe) {}

    /**
     * Full or partial refund. Always records a `refunds` row — with status
     * "succeeded" once Stripe confirms, or "failed" with the error kept so
     * financial records always reconcile, even against a mocked/unconfigured
     * Stripe account.
     */
    public function refund(Order $order, float $amount, string $reason, ?int $initiatedBy = null): Refund
    {
        $refund = Refund::create([
            'order_id' => $order->id,
            'amount' => $amount,
            'reason' => $reason,
            'status' => 'pending',
            'initiated_by' => $initiatedBy,
        ]);

        if (! $this->stripe->configured() || ! $order->payment) {
            $refund->update(['status' => 'failed']);

            return $refund;
        }

        try {
            $client = new StripeClient(config('services.stripe.secret'));

            $stripeRefund = $client->refunds->create([
                'payment_intent' => $order->payment->stripe_payment_intent_id,
                'amount' => (int) round($amount * 100),
            ], ['idempotency_key' => 'refund-'.$refund->id]);

            $refund->update([
                'stripe_refund_id' => $stripeRefund->id,
                'status' => 'succeeded',
            ]);

            DB::transaction(function () use ($order) {
                $locked = Order::where('id', $order->id)->lockForUpdate()->first();
                $isFullRefund = $locked->totalRefunded() >= (float) $locked->total;

                $locked->update(['status' => $isFullRefund ? 'refunded' : $locked->status]);
            });
        } catch (\Throwable $e) {
            $refund->update(['status' => 'failed']);

            throw new VendorApiException("Stripe refund failed: {$e->getMessage()}");
        }

        return $refund;
    }
}
