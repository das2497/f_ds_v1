<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Subcategory;
use App\Services\GoogleSheets\GoogleSheetsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleSheetsCategoryLookupTest extends TestCase
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
                ['properties' => ['sheetId' => 3, 'title' => GoogleSheetsService::CATEGORIES_SHEET]],
            ]]),
            '*' => Http::response(['ok' => true]),
        ]);
    }

    public function test_syncing_the_category_lookup_writes_pairs_and_sets_dropdown_validation(): void
    {
        $this->configureSheets();
        $this->fakeSheetsApi();

        $toys = Category::create(['name' => 'Toys & Games', 'slug' => 'toys-games']);
        Subcategory::create(['category_id' => $toys->id, 'name' => 'Outdoor Play', 'slug' => 'outdoor-play']);
        Subcategory::create(['category_id' => $toys->id, 'name' => 'Puzzles', 'slug' => 'puzzles']);
        Category::create(['name' => 'Home & Living', 'slug' => 'home-living']);

        app(GoogleSheetsService::class)->syncCategoryLookup();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'Categories%21A1')) {
                return false;
            }

            $values = $request->data()['values'] ?? [];

            return $values[0] === GoogleSheetsService::CATEGORY_LOOKUP_HEADERS
                && in_array(['Toys & Games', 'Outdoor Play'], $values, true)
                && in_array(['Toys & Games', 'Puzzles'], $values, true)
                && in_array(['Home & Living', ''], $values, true);
        });

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), ':batchUpdate') || str_contains($request->url(), 'values:batchUpdate')) {
                return false;
            }

            $requests = $request->data()['requests'] ?? [];

            return collect($requests)->contains(fn ($r) => isset($r['setDataValidation']));
        });
    }

    public function test_saving_a_category_triggers_a_sheet_sync(): void
    {
        $this->configureSheets();
        $this->fakeSheetsApi();

        Category::create(['name' => 'Toys & Games', 'slug' => 'toys-games']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'Categories%21A1'));
    }

    public function test_saving_a_subcategory_triggers_a_sheet_sync(): void
    {
        $category = Category::create(['name' => 'Toys & Games', 'slug' => 'toys-games']);

        $this->configureSheets();
        $this->fakeSheetsApi();

        Subcategory::create(['category_id' => $category->id, 'name' => 'Outdoor Play', 'slug' => 'outdoor-play']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'Categories%21A1'));
    }

    public function test_category_sync_is_skipped_when_sheets_not_configured(): void
    {
        Http::fake();

        Category::create(['name' => 'Toys & Games', 'slug' => 'toys-games']);

        Http::assertNothingSent();
    }
}
