<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;

/**
 * Server-side GA4 Measurement Protocol — an *additional* channel alongside
 * browser gtag.js tagging (1_0.txt §7), not a replacement. The order's own
 * `order_number` doubles as GA4's `transaction_id`, so the browser-side
 * purchase hit and this server-side hit naturally dedupe in GA4 DebugView
 * without any extra coordination.
 *
 * Credentials are read from admin/settings-gateways (stored encrypted in
 * app_settings) first, falling back to .env — same pattern as CJService.
 */
class Ga4MeasurementProtocolService
{
    private const BASE_URL = 'https://www.google-analytics.com/mp/collect';

    public function configured(): bool
    {
        return filled($this->measurementId()) && filled($this->apiSecret());
    }

    private function measurementId(): ?string
    {
        return AppSetting::get('ga4.measurement_id') ?: config('services.ga4.measurement_id');
    }

    private function apiSecret(): ?string
    {
        return AppSetting::get('ga4.api_secret') ?: config('services.ga4.api_secret');
    }

    public function sendPurchase(AnalyticsEvent $event): bool
    {
        return $this->send('purchase', $event, [
            'transaction_id' => $event->order->order_number,
            'currency' => $event->currency,
            'value' => (float) $event->value,
        ]);
    }

    public function sendRefund(AnalyticsEvent $event): bool
    {
        return $this->send('refund', $event, [
            'transaction_id' => $event->order->order_number,
            'currency' => $event->currency,
            'value' => (float) $event->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function send(string $name, AnalyticsEvent $event, array $params): bool
    {
        if (! $this->configured()) {
            return false;
        }

        $response = Http::timeout(10)->post(self::BASE_URL.'?'.http_build_query([
            'measurement_id' => $this->measurementId(),
            'api_secret' => $this->apiSecret(),
        ]), [
            'client_id' => $event->session_id,
            'events' => [[
                'name' => $name,
                'params' => [
                    ...$params,
                    'campaign' => $event->utm_campaign,
                    'source' => $event->utm_source,
                    'medium' => $event->utm_medium,
                ],
            ]],
        ]);

        return $response->successful();
    }
}
