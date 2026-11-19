<?php

namespace Tests\Feature\Admin;

use App\Models\AppSetting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GatewaySettingsGoogleSheetsTest extends TestCase
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
            'client_email' => 'sync@example.iam.gserviceaccount.com',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nfake\n-----END PRIVATE KEY-----\n",
        ]);
    }

    public function test_the_page_lists_the_sheets_and_aliexpress_fields(): void
    {
        $response = $this->actingAs($this->superAdmin())->get(route('admin.settings-gateways'));

        $response->assertOk();
        $response->assertSee('name="google_sheets_spreadsheet_id"', false);
        $response->assertSee('name="google_sheets_credentials_json"', false);
        $response->assertSee('name="google_sheets_sync_token"', false);
        $response->assertSee('name="aliexpress_extension_token"', false);
    }

    public function test_saving_a_valid_service_account_key_persists_the_sheets_settings(): void
    {
        $this->actingAs($this->superAdmin())->put(route('admin.settings-gateways.update'), [
            'google_sheets_spreadsheet_id' => 'abc123',
            'google_sheets_credentials_json' => $this->serviceAccountJson(),
            'google_sheets_sync_token' => 'sheet-secret',
            'aliexpress_extension_token' => 'ext-secret',
        ])->assertRedirect();

        $this->assertSame('abc123', AppSetting::get('google_sheets.spreadsheet_id'));
        $this->assertSame($this->serviceAccountJson(), AppSetting::get('google_sheets.credentials_json'));
        $this->assertSame('sheet-secret', AppSetting::get('google_sheets.sync_token'));
        $this->assertSame('ext-secret', AppSetting::get('aliexpress_extension.token'));
    }

    public function test_a_json_blob_missing_service_account_fields_is_rejected(): void
    {
        $this->actingAs($this->superAdmin())->put(route('admin.settings-gateways.update'), [
            'google_sheets_credentials_json' => json_encode(['foo' => 'bar']),
        ])->assertSessionHasErrors('google_sheets_credentials_json');

        $this->assertNull(AppSetting::get('google_sheets.credentials_json'));
    }

    public function test_malformed_json_is_rejected(): void
    {
        $this->actingAs($this->superAdmin())->put(route('admin.settings-gateways.update'), [
            'google_sheets_credentials_json' => 'not json at all',
        ])->assertSessionHasErrors('google_sheets_credentials_json');
    }

    public function test_leaving_the_sheets_fields_blank_keeps_their_existing_values(): void
    {
        AppSetting::set('google_sheets.spreadsheet_id', 'existing-id');

        $this->actingAs($this->superAdmin())->put(route('admin.settings-gateways.update'), [
            'google_sheets_spreadsheet_id' => '',
        ])->assertRedirect();

        $this->assertSame('existing-id', AppSetting::get('google_sheets.spreadsheet_id'));
    }
}
