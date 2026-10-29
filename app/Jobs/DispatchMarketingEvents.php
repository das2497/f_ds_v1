<?php

namespace App\Jobs;

use App\Models\AnalyticsEvent;
use App\Services\Analytics\Ga4MeasurementProtocolService;
use App\Services\Analytics\MetaCapiService;
use App\Services\Analytics\PiiHasher;
use App\Services\Analytics\TikTokEventsApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends a `purchase`/`refund` conversion to every configured marketing
 * platform. Each platform is independent — one being unreachable or
 * unconfigured never blocks the others — and a delivery timestamp column
 * is only set on success, so a retried/re-queued job skips platforms that
 * already received this event instead of double-sending.
 */
class DispatchMarketingEvents implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $analyticsEventId) {}

    public function handle(
        Ga4MeasurementProtocolService $ga4,
        MetaCapiService $meta,
        TikTokEventsApiService $tiktok,
        PiiHasher $hasher,
    ): void {
        $event = AnalyticsEvent::with('order')->find($this->analyticsEventId);

        if (! $event || ! in_array($event->event_name, ['purchase', 'refund'], true)) {
            return;
        }

        $isPurchase = $event->event_name === 'purchase';

        if (! $event->ga4_sent_at && $ga4->configured()) {
            $sent = $isPurchase ? $ga4->sendPurchase($event) : $ga4->sendRefund($event);
            if ($sent) {
                $event->update(['ga4_sent_at' => now()]);
            }
        }

        if (! $event->meta_sent_at && $meta->configured()) {
            $sent = $isPurchase ? $meta->sendPurchase($event, $hasher) : $meta->sendRefund($event, $hasher);
            if ($sent) {
                $event->update(['meta_sent_at' => now()]);
            }
        }

        if (! $event->tiktok_sent_at && $tiktok->configured()) {
            $sent = $isPurchase ? $tiktok->sendPurchase($event, $hasher) : $tiktok->sendRefund($event, $hasher);
            if ($sent) {
                $event->update(['tiktok_sent_at' => now()]);
            }
        }
    }
}
