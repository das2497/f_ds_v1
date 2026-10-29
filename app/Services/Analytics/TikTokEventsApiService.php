<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;

/**
 * TikTok Events API. Like Meta, the `event_id` here must match the
 * browser-side `ttq.track('CompletePayment', ..., {event_id: ...})` call
 * for the same purchase (1_0.txt §7 — explicitly called out for TikTok).
 *
 * Credentials are read from admin/settings-gateways (stored encrypted in
 * app_settings) first, falling back to .env — same pattern as CJService.
 */
class TikTokEventsApiService
{
    private const BASE_URL = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';

    public function configured(): bool
    {
        return filled($this->pixelCode()) && filled($this->accessToken());
    }

    private function pixelCode(): ?string
    {
        return AppSetting::get('tiktok.pixel_code') ?: config('services.tiktok.pixel_code');
    }

    private function accessToken(): ?string
    {
        return AppSetting::get('tiktok.access_token') ?: config('services.tiktok.access_token');
    }

    public function sendPurchase(AnalyticsEvent $event, PiiHasher $hasher): bool
    {
        return $this->send('CompletePayment', $event, $hasher);
    }

    public function sendRefund(AnalyticsEvent $event, PiiHasher $hasher): bool
    {
        return $this->send('Refund', $event, $hasher);
    }

    private function send(string $eventName, AnalyticsEvent $event, PiiHasher $hasher): bool
    {
        if (! $this->configured()) {
            return false;
        }

        $order = $event->order;

        $userData = array_filter([
            'email' => $order ? $hasher->email($order->email) : null,
            'phone' => $order ? $hasher->phone($order->phone) : null,
            'ttclid' => $order?->ttclid,
        ]);

        $payload = [
            'event_source' => 'web',
            'event_source_id' => $this->pixelCode(),
            'data' => [[
                'event' => $eventName,
                'event_id' => $event->event_id,
                'event_time' => $event->created_at->timestamp,
                'user' => (object) $userData,
                'properties' => [
                    'currency' => $event->currency,
                    'value' => (float) $event->value,
                ],
            ]],
        ];

        $response = Http::timeout(10)
            ->withHeaders(['Access-Token' => $this->accessToken()])
            ->post(self::BASE_URL, $payload);

        return $response->successful();
    }
}
