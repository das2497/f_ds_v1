<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Product;
use App\Models\User;
use App\Services\GoogleSheets\GoogleSheetsService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleSheetsBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function configureSheets(): void
    {
        AppSetting::set('google_sheets.spreadsheet_id', 'sheet-123');
        AppSetting::set('google_sheets.credentials_json', json_encode([
            'client_email' => 'sync@example.iam.gserviceaccount.com',
            'private_key' => $this->fakePrivateKeyPem(),
        ]));
    }

    private function fakePrivateKeyPem(): string
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, $pem);

        return $pem;
    }

    private function fakeSheetsApi(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token']),
            '*fields=sheets.properties*' => Http::response(['sheets' => [
                ['properties' => ['sheetId' => 1, 'title' => GoogleSheetsService::PRODUCTS_SHEET]],
                ['properties' => ['sheetId' => 2, 'title' => GoogleSheetsService::VARIANTS_SHEET]],
            ]]),
            '*values/Products%211%3A1*' => Http::response(['values' => [GoogleSheetsService::PRODUCT_HEADERS]]),
            '*values/Variants%211%3A1*' => Http::response(['values' => [GoogleSheetsService::VARIANT_HEADERS]]),
            '*values/Products%21A%3AA*' => Http::response(['values' => []]),
            '*values/Variants%21A%3AA*' => Http::response(['values' => []]),
            '*:append*' => Http::response(['updates' => []]),
        ]);
    }

    private function productWithVariants(string $title): Product
    {
        $product = Product::create([
            'source_vendor' => 'manual',
            'title' => $title,
            'slug' => str($title)->slug(),
            'price' => 10,
            'images' => [],
            'is_active' => true,
        ]);

        $product->variants()->create([
            'sku' => strtoupper(str($title)->slug()).'-1',
            'option_label' => 'Default',
            'cost_price' => 5,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        return $product;
    }

    public function test_the_command_refuses_to_run_when_sheets_is_not_configured(): void
    {
        $exitCode = Artisan::call('sheets:backfill');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString("isn't configured", Artisan::output());
    }

    public function test_the_command_pushes_every_existing_product_and_variant(): void
    {
        $this->configureSheets();
        $this->fakeSheetsApi();

        $this->productWithVariants('First Product');
        $this->productWithVariants('Second Product');

        $exitCode = Artisan::call('sheets:backfill');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Pushed 2 products and 2 variants', Artisan::output());

        Http::assertSent(fn ($request) => str_contains($request->url(), ':append'));
    }

    public function test_a_super_admin_can_trigger_the_backfill_from_the_settings_page(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $this->configureSheets();
        $this->fakeSheetsApi();
        $this->productWithVariants('Third Product');

        $response = $this->actingAs($admin)->post(route('admin.settings-gateways.google-sheets-backfill'));

        $response->assertRedirect();
        $response->assertSessionHas('status');
    }

    public function test_a_non_super_admin_cannot_trigger_the_backfill(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)->post(route('admin.settings-gateways.google-sheets-backfill'))->assertForbidden();
    }
}
