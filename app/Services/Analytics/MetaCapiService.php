<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;

/**
 * Meta Conversions API. `event_id` on this call must match the browser
 * pixel's `fbq('track', 'Purchase', ..., {eventID: ...})` call for the same
 * purchase so Meta dedupes the two instead of double-counting
 * (1_0.txt §7 — event_id matching between browser and server).
 *
 * Credentials are read from admin/settings-gateways (stored encrypted in
 * app_settings) first, falling back to .env — same pattern as CJService.
 */
class MetaCapiService
{
    private function baseUrl(): string
    {
        return 'https://graph.facebook.com/v19.0/'.$this->pixelId().'/events';
    }

    public function configured(): bool
    {
        return filled($this->pixelId()) && filled($this->accessToken());
    }

    private function pixelId(): ?string
    {
        return AppSetting::get('meta.pixel_id') ?: config('services.meta.pixel_id');
    }

    private function accessToken(): ?string
    {
        return AppSetting::get('meta.access_token') ?: config('services.meta.access_token');
    }

    public function sendPurchase(AnalyticsEvent $event, PiiHasher $hasher): bool
    {
        return $this->send('Purchase', $event, $hasher);
    }

    public function sendRefund(AnalyticsEvent $event, PiiHasher $hasher): bool
    {
        // Meta has no first-class "Refund" standard event — tracked as a
        // custom event so it doesn't pollute Purchase-optimized ad sets.
        return $this->send('Refund', $event, $hasher, custom: true);
    }

    private function send(string $eventName, AnalyticsEvent $event, PiiHasher $hasher, bool $custom = false): bool
    {
        if (! $this->configured()) {
            return false;
        }

        $order = $event->order;

        $userData = array_filter([
            'em' => $order ? [$hasher->email($order->email)] : null,
            'ph' => $order ? [$hasher->phone($order->phone)] : null,
            'fbp' => $order?->fbp,
            'fbc' => $order?->fbc,
        ]);

        $payload = [
            'data' => [[
                'event_name' => $eventName,
                'event_time' => $event->created_at->timestamp,
                'event_id' => $event->event_id,
                'action_source' => 'website',
                'user_data' => (object) $userData,
                'custom_data' => array_filter([
                    'currency' => $event->currency,
                    'value' => (float) $event->value,
                    'event_type' => $custom ? $eventName : null,
                ]),
            ]],
            'access_token' => $this->accessToken(),
        ];

        $response = Http::timeout(10)->post($this->baseUrl(), $payload);

        return $response->successful();
    }
}
