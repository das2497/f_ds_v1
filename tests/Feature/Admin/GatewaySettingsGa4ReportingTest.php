<?php

namespace Tests\Feature\Admin;

use App\Models\AppSetting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GatewaySettingsGa4ReportingTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('super-admin');

        return $user;
    }

    private function serviceAccountJson(): string
    {
        return json_encode([
            'type' => 'service_account',
            'client_email' => 'reporting@example.iam.gserviceaccount.com',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nfake\n-----END PRIVATE KEY-----\n",
        ]);
    }

    public function test_the_page_lists_the_ga4_reporting_fields(): void
    {
        $response = $this->actingAs($this->superAdmin())->get(route('admin.settings-gateways'));

        $response->assertOk();
        $response->assertSee('name="ga4_property_id"', false);
        $response->assertSee('name="ga4_reporting_credentials_json"', false);
    }

    public function test_saving_a_valid_service_account_key_persists_the_ga4_reporting_settings(): void
    {
        $this->actingAs($this->superAdmin())->put(route('admin.settings-gateways.update'), [
            'ga4_property_id' => '123456789',
            'ga4_reporting_credentials_json' => $this->serviceAccountJson(),
        ])->assertRedirect();

        $this->assertSame('123456789', AppSetting::get('ga4.property_id'));
        $this->assertSame($this->serviceAccountJson(), AppSetting::get('ga4.reporting_credentials_json'));
    }

    public function test_a_json_blob_missing_service_account_fields_is_rejected(): void
    {
        $this->actingAs($this->superAdmin())->put(route('admin.settings-gateways.update'), [
            'ga4_reporting_credentials_json' => json_encode(['foo' => 'bar']),
        ])->assertSessionHasErrors('ga4_reporting_credentials_json');

        $this->assertNull(AppSetting::get('ga4.reporting_credentials_json'));
    }

    public function test_leaving_the_ga4_reporting_fields_blank_keeps_their_existing_values(): void
    {
        AppSetting::set('ga4.property_id', 'existing-id');

        $this->actingAs($this->superAdmin())->put(route('admin.settings-gateways.update'), [
            'ga4_property_id' => '',
        ])->assertRedirect();

        $this->assertSame('existing-id', AppSetting::get('ga4.property_id'));
    }
}
