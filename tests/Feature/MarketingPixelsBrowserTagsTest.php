<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The GA4/Meta/TikTok *services* (Ga4MeasurementProtocolService etc.) fall
 * back from AppSetting to .env config — see MarketingAttributionCredentialsTest.
 * The browser-side pixel tags (partials/marketing-pixels.blade.php,
 * store/order-success.blade.php) must honor the same fallback, or a pixel
 * configured only through the admin panel silently never fires in the
 * browser even though the server-side services think it's configured.
 */
class MarketingPixelsBrowserTagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_storefront_loads_ga4_meta_and_tiktok_tags_from_admin_saved_settings(): void
    {
        AppSetting::set('ga4.measurement_id', 'G-ADMINSAVED');
        AppSetting::set('meta.pixel_id', 'admin-meta-pixel');
        AppSetting::set('tiktok.pixel_code', 'admin-tiktok-code');

        $response = $this->withCookie('analytics_consent', 'granted')->get('/');

        $response->assertOk();
        $response->assertSee('googletagmanager.com/gtag/js?id=G-ADMINSAVED', false);
        $response->assertSee("gtag('config', 'G-ADMINSAVED')", false);
        $response->assertSee("fbq('init', 'admin-meta-pixel')", false);
        $response->assertSee("ttq.load('admin-tiktok-code')", false);
    }

    public function test_the_storefront_shows_no_pixel_tags_without_consent_even_when_configured(): void
    {
        AppSetting::set('ga4.measurement_id', 'G-ADMINSAVED');

        $response = $this->withCookie('analytics_consent', 'denied')->get('/');

        $response->assertOk();
        $response->assertDontSee('googletagmanager.com/gtag/js', false);
    }
}
