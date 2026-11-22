<?php

namespace Tests\Feature\Admin;

use App\Models\AppSetting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Ga4DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function configureGa4Reporting(): void
    {
        AppSetting::set('ga4.property_id', '123456789');
        AppSetting::set('ga4.reporting_credentials_json', json_encode([
            'client_email' => 'reporting@example.iam.gserviceaccount.com',
            'private_key' => $this->fakePrivateKeyPem(),
        ]));
    }

    private function fakePrivateKeyPem(): string
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, $pem);

        return $pem;
    }

    /**
     * Fakes GA4's token endpoint and the Data API's runReport endpoint,
     * building a response shaped to whatever dimensions/metrics the
     * service actually asked for — the real API echoes back the
     * dimension/metric headers in request order, so a static fixture
     * would silently mismatch them.
     */
    private function fakeGa4ReportingApi(): void
    {
        $fixtures = [
            '' => [[], ['totalUsers' => '120', 'sessions' => '150', 'screenPageViews' => '300', 'averageSessionDuration' => '45.5', 'bounceRate' => '0.42', 'keyEvents' => '10', 'activeUsers' => '7']],
            'date' => [
                ['20240601', '20240602'],
                ['totalUsers' => ['60', '65'], 'sessions' => ['70', '75']],
            ],
            'sessionDefaultChannelGroup' => [
                ['Organic Search', 'Paid Search'],
                ['sessions' => ['100', '50'], 'totalUsers' => ['90', '40'], 'keyEvents' => ['8', '2']],
            ],
            'deviceCategory' => [
                ['mobile', 'desktop'],
                ['sessions' => ['90', '60'], 'totalUsers' => ['80', '55']],
            ],
            'country' => [
                ['United States', 'Sri Lanka'],
                ['totalUsers' => ['70', '30']],
            ],
            'pagePath' => [
                ['/', '/products'],
                ['screenPageViews' => ['200', '100']],
            ],
            'unifiedScreenName' => [
                ['Home', 'Product Page'],
                ['activeUsers' => ['5', '3']],
            ],
        ];

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token']),
            'analyticsdata.googleapis.com/*' => function (ClientRequest $request) use ($fixtures) {
                $body = $request->data();
                $dimensionNames = collect($body['dimensions'] ?? [])->pluck('name')->all();
                $metricNames = collect($body['metrics'] ?? [])->pluck('name')->all();
                $key = $dimensionNames[0] ?? '';

                [$dimensionValues, $metricColumns] = $fixtures[$key];
                $rowCount = $key === '' ? 1 : count($dimensionValues);

                $rows = [];
                for ($i = 0; $i < $rowCount; $i++) {
                    $rows[] = [
                        'dimensionValues' => array_map(fn () => ['value' => $dimensionValues[$i]], $dimensionNames),
                        'metricValues' => array_map(fn ($name) => ['value' => $key === '' ? $metricColumns[$name] : $metricColumns[$name][$i]], $metricNames),
                    ];
                }

                return Http::response([
                    'dimensionHeaders' => array_map(fn ($name) => ['name' => $name], $dimensionNames),
                    'metricHeaders' => array_map(fn ($name) => ['name' => $name], $metricNames),
                    'rows' => $rows,
                ]);
            },
        ]);
    }

    public function test_the_page_prompts_setup_when_ga4_reporting_is_not_configured(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.analytics-ga4'));

        $response->assertOk();
        $response->assertSee("GA4 reporting isn't connected yet", false);
    }

    public function test_the_page_shows_live_ga4_data_when_configured(): void
    {
        $this->configureGa4Reporting();
        $this->fakeGa4ReportingApi();

        $response = $this->actingAs($this->admin())->get(route('admin.analytics-ga4'));

        $response->assertOk();
        $response->assertSee('120'); // totalUsers
        $response->assertSee('150'); // sessions
        $response->assertSee('300'); // screenPageViews
        $response->assertSee('Organic Search');
        $response->assertSee('United States');
        $response->assertSee('/products');
    }

    public function test_the_page_shows_an_error_state_when_the_ga4_api_call_fails(): void
    {
        $this->configureGa4Reporting();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token']),
            'analyticsdata.googleapis.com/*' => Http::response(['error' => 'denied'], 403),
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.analytics-ga4'));

        $response->assertOk();
        $response->assertSee("Couldn't load Google Analytics", false);
    }

    public function test_the_realtime_endpoint_returns_active_users_and_top_pages(): void
    {
        $this->configureGa4Reporting();
        $this->fakeGa4ReportingApi();

        $response = $this->actingAs($this->admin())->getJson(route('admin.analytics-ga4.realtime'));

        $response->assertOk()->assertJson([
            'configured' => true,
            'activeUsers' => 7,
            'topPages' => [
                ['page' => 'Home', 'activeUsers' => 5],
                ['page' => 'Product Page', 'activeUsers' => 3],
            ],
        ]);
    }

    public function test_the_realtime_endpoint_reports_not_configured(): void
    {
        $response = $this->actingAs($this->admin())->getJson(route('admin.analytics-ga4.realtime'));

        $response->assertOk()->assertExactJson(['configured' => false]);
    }

    public function test_the_realtime_endpoint_reports_an_error_without_crashing(): void
    {
        $this->configureGa4Reporting();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token']),
            'analyticsdata.googleapis.com/*' => Http::response(['error' => 'denied'], 403),
        ]);

        $response = $this->actingAs($this->admin())->getJson(route('admin.analytics-ga4.realtime'));

        $response->assertStatus(502)->assertJson(['configured' => true, 'error' => true]);
    }
}
