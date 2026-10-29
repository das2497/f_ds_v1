<?php

namespace Tests\Feature\Admin;

use App\Models\AppSetting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GatewaySettingsMarketingTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('super-admin');

        return $user;
    }

    public function test_the_page_lists_google_ga4_meta_and_tiktok_fields(): void
    {
        $response = $this->actingAs($this->superAdmin())->get(route('admin.settings-gateways'));

        $response->assertOk();
        $response->assertSee('name="google_client_id"', false);
        $response->assertSee('name="google_client_secret"', false);
        $response->assertSee('name="ga4_measurement_id"', false);
        $response->assertSee('name="ga4_api_secret"', false);
        $response->assertSee('name="meta_pixel_id"', false);
        $response->assertSee('name="meta_access_token"', false);
        $response->assertSee('name="tiktok_pixel_code"', false);
        $response->assertSee('name="tiktok_access_token"', false);
    }

    public function test_saving_marketing_and_google_credentials_persists_them_as_app_settings(): void
    {
        $this->actingAs($this->superAdmin())->put(route('admin.settings-gateways.update'), [
            'google_client_id' => 'google-client-id',
            'google_client_secret' => 'google-client-secret',
            'ga4_measurement_id' => 'G-ABC123',
            'ga4_api_secret' => 'ga4-secret',
            'meta_pixel_id' => '123456789',
            'meta_access_token' => 'meta-token',
            'tiktok_pixel_code' => 'CXXXXXXX',
            'tiktok_access_token' => 'tiktok-token',
        ])->assertRedirect();

        $this->assertSame('google-client-id', AppSetting::get('google.client_id'));
        $this->assertSame('google-client-secret', AppSetting::get('google.client_secret'));
        $this->assertSame('G-ABC123', AppSetting::get('ga4.measurement_id'));
        $this->assertSame('ga4-secret', AppSetting::get('ga4.api_secret'));
        $this->assertSame('123456789', AppSetting::get('meta.pixel_id'));
        $this->assertSame('meta-token', AppSetting::get('meta.access_token'));
        $this->assertSame('CXXXXXXX', AppSetting::get('tiktok.pixel_code'));
        $this->assertSame('tiktok-token', AppSetting::get('tiktok.access_token'));
    }

    public function test_leaving_a_marketing_field_blank_keeps_its_existing_value(): void
    {
        AppSetting::set('ga4.measurement_id', 'G-EXISTING');

        $this->actingAs($this->superAdmin())->put(route('admin.settings-gateways.update'), [
            'ga4_measurement_id' => '',
        ])->assertRedirect();

        $this->assertSame('G-EXISTING', AppSetting::get('ga4.measurement_id'));
    }
}
