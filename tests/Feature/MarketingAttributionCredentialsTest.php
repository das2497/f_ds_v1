<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\AppSetting;
use App\Services\Analytics\Ga4MeasurementProtocolService;
use App\Services\Analytics\MetaCapiService;
use App\Services\Analytics\PiiHasher;
use App\Services\Analytics\TikTokEventsApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Credentials for GA4 / Meta / TikTok come from the admin gateway settings
 * page (encrypted app_settings rows) first, falling back to .env — same
 * pattern already used for CJ and Printful.
 */
class MarketingAttributionCredentialsTest extends TestCase
{
    use RefreshDatabase;

    private function event(): AnalyticsEvent
    {
        return AnalyticsEvent::create([
            'session_id' => 'sess_test',
            'event_name' => 'purchase',
            'event_id' => 'evt_test_1',
            'value' => 42.50,
            'currency' => 'USD',
        ]);
    }

    public function test_ga4_is_not_configured_until_admin_saves_both_credentials(): void
    {
        $service = app(Ga4MeasurementProtocolService::class);

        $this->assertFalse($service->configured());

        AppSetting::set('ga4.measurement_id', 'G-TESTID');
        $this->assertFalse($service->configured());

        AppSetting::set('ga4.api_secret', 'secret123');
        $this->assertTrue($service->configured());
    }

    public function test_meta_capi_sends_using_the_admin_saved_credentials(): void
    {
        AppSetting::set('meta.pixel_id', 'admin-pixel-id');
        AppSetting::set('meta.access_token', 'admin-access-token');
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);

        $sent = app(MetaCapiService::class)->sendPurchase($this->event(), new PiiHasher);

        $this->assertTrue($sent);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'admin-pixel-id')
                && $request['access_token'] === 'admin-access-token';
        });
    }

    public function test_tiktok_events_api_sends_using_the_admin_saved_credentials(): void
    {
        AppSetting::set('tiktok.pixel_code', 'admin-pixel-code');
        AppSetting::set('tiktok.access_token', 'admin-access-token');
        Http::fake(['business-api.tiktok.com/*' => Http::response(['code' => 0])]);

        $sent = app(TikTokEventsApiService::class)->sendPurchase($this->event(), new PiiHasher);

        $this->assertTrue($sent);
        Http::assertSent(function ($request) {
            return $request->hasHeader('Access-Token', 'admin-access-token')
                && $request['event_source_id'] === 'admin-pixel-code';
        });
    }
}
