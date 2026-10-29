<?php

namespace App\Services\Analytics;

use App\Jobs\DispatchMarketingEvents;
use App\Models\AnalyticsEvent;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Records every tracking event locally (1_0.txt §7 table). `purchase` and
 * `refund` are also dispatched to GA4/Meta/TikTok server-side — the other
 * events stay first-party only, feeding the UTM/demand admin reports.
 */
class EventTracker
{
    public function __construct(private readonly AttributionService $attribution) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function track(Request $request, string $eventName, array $attributes = []): AnalyticsEvent
    {
        $touch = $this->attribution->currentTouch($request);

        return AnalyticsEvent::create([
            'session_id' => $this->attribution->sessionId($request),
            'user_id' => $request->user()?->id,
            'event_name' => $eventName,
            'utm_source' => $touch['source'],
            'utm_medium' => $touch['medium'],
            'utm_campaign' => $touch['campaign'],
            'utm_ad_id' => $touch['ad_id'],
            'path' => $request->path(),
            ...$attributes,
        ]);
    }

    /**
     * `purchase` fires from server-verified payment confirmation (Stripe
     * webhook, admin actions) — contexts with no customer browser request
     * to read cookies from. Attribution instead comes from the order's own
     * session_id/last-touch columns, snapshotted at checkout in the
     * customer's real request (see CheckoutController::store()).
     *
     * Idempotent: `purchase-{order_number}` is always the same event_id for
     * this order, so a retried webhook never creates a duplicate row or
     * re-sends to GA4/Meta/TikTok (the unique index on
     * [event_name, event_id] enforces this at the DB level).
     */
    public function trackPurchase(Order $order): void
    {
        $eventId = "purchase-{$order->order_number}";

        if (AnalyticsEvent::where('event_name', 'purchase')->where('event_id', $eventId)->exists()) {
            return;
        }

        $event = AnalyticsEvent::create([
            'session_id' => $order->session_id ?? (string) $order->id,
            'user_id' => $order->user_id,
            'event_name' => 'purchase',
            'event_id' => $eventId,
            'utm_source' => $order->last_touch_source,
            'utm_medium' => $order->last_touch_medium,
            'utm_campaign' => $order->last_touch_campaign,
            'utm_ad_id' => $order->last_touch_ad_id,
            'order_id' => $order->id,
            'value' => $order->total,
            'currency' => $order->currency,
        ]);

        DispatchMarketingEvents::dispatch($event->id);
    }

    public function trackRefund(Order $order, float $amount): void
    {
        $eventId = "refund-{$order->order_number}-".now()->timestamp;

        $event = AnalyticsEvent::create([
            'session_id' => $order->session_id ?? (string) $order->id,
            'user_id' => $order->user_id,
            'event_name' => 'refund',
            'event_id' => $eventId,
            'utm_source' => $order->last_touch_source,
            'utm_medium' => $order->last_touch_medium,
            'utm_campaign' => $order->last_touch_campaign,
            'utm_ad_id' => $order->last_touch_ad_id,
            'order_id' => $order->id,
            'value' => $amount,
            'currency' => $order->currency,
        ]);

        DispatchMarketingEvents::dispatch($event->id);
    }
}
