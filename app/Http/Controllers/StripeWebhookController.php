<?php

namespace App\Http\Controllers;

use App\Mail\PaymentVerifiedMail;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\Analytics\EventTracker;
use App\Services\Payment\StripePaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly StripePaymentService $stripe,
        private readonly EventTracker $tracker,
    ) {}

    public function handle(Request $request): Response
    {
        $secret = $this->stripe->webhookSecret();

        if (blank($secret)) {
            return response('Webhook secret not configured.', 503);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
                $secret
            );
        } catch (SignatureVerificationException|\UnexpectedValueException $e) {
            Log::warning('Stripe webhook signature verification failed', ['message' => $e->getMessage()]);

            return response('Invalid signature.', 400);
        }

        // Idempotent: a stripe_event_id already recorded means this event was
        // already processed — never apply its side effects twice.
        if (WebhookEvent::where('stripe_event_id', $event->id)->exists()) {
            return response('Already processed.', 200);
        }

        DB::transaction(function () use ($event) {
            WebhookEvent::create([
                'stripe_event_id' => $event->id,
                'type' => $event->type,
                'payload' => $event->toArray(),
                'processed_at' => now(),
            ]);

            match ($event->type) {
                'payment_intent.succeeded' => $this->markOrderPaid($event),
                'payment_intent.payment_failed' => $this->markPaymentFailed($event),
                default => null,
            };
        });

        return response('OK', 200);
    }

    private function markOrderPaid(Event $event): void
    {
        $intentId = $event->data->object->id;

        $payment = Payment::where('stripe_payment_intent_id', $intentId)->lockForUpdate()->first();

        if (! $payment) {
            return;
        }

        $payment->update(['status' => 'succeeded']);

        $order = $payment->order()->lockForUpdate()->first();

        if ($order && $order->status === 'pending_payment') {
            $order->update([
                'status' => 'paid',
                'release_at' => now()->addHours(2),
            ]);

            $this->clearCartFor($order);

            Mail::to($order->email)->queue(new PaymentVerifiedMail($order));

            $this->tracker->trackPurchase($order);
        }
    }

    private function clearCartFor(Order $order): void
    {
        $cart = $order->user_id
            ? Cart::where('user_id', $order->user_id)->first()
            : Cart::where('session_id', $order->guest_token)->first();

        $cart?->items()->delete();
    }

    private function markPaymentFailed(Event $event): void
    {
        $intentId = $event->data->object->id;

        Payment::where('stripe_payment_intent_id', $intentId)->update(['status' => 'failed']);
    }
}
