<?php

namespace Tests\Feature\Api;

use App\Models\AppSetting;
use App\Models\Product;
use App\Models\VendorProductCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AliExpressImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.aliexpress_extension.token' => 'test-extension-token']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'vendor_external_id' => '1005006123456789',
            'title' => 'Wireless Earbuds',
            'description' => 'Great sound.',
            'source_url' => 'https://www.aliexpress.com/item/1005006123456789.html',
            'preview_image_url' => null,
            'images' => [],
            'category_hint' => 'Electronics',
            'variants' => [
                ['option_label' => 'Black', 'color' => 'Black', 'cost_price' => 8.50],
                ['option_label' => 'White', 'color' => 'White', 'cost_price' => 8.50],
            ],
        ], $overrides);
    }

    public function test_it_rejects_a_request_without_a_valid_token(): void
    {
        $this->postJson('/api/aliexpress/products', $this->payload())->assertUnauthorized();
        $this->assertDatabaseMissing('products', ['title' => 'Wireless Earbuds']);
    }

    public function test_it_imports_a_scraped_product_as_inactive(): void
    {
        $response = $this->postJson('/api/aliexpress/products', $this->payload(), [
            'Authorization' => 'Bearer test-extension-token',
        ]);

        $response->assertCreated()->assertJson(['status' => 'created']);

        $product = Product::where('title', 'Wireless Earbuds')->firstOrFail();
        $this->assertFalse($product->is_active);
        $this->assertSame('aliexpress', $product->source_vendor);
        $this->assertSame('https://www.aliexpress.com/item/1005006123456789.html', $product->source_url);
        // Default profit margin is 150% (cost * 2.5) — see PricingSettingsController.
        $this->assertSame('21.25', (string) $product->price);
        $this->assertCount(2, $product->variants);
        $this->assertTrue($product->variants->every(fn ($variant) => $variant->is_active));
        $this->assertTrue($product->variants->every(fn ($variant) => (string) $variant->price === '21.25'));

        $candidate = VendorProductCandidate::where('vendor', 'aliexpress')
            ->where('vendor_external_id', '1005006123456789')
            ->firstOrFail();
        $this->assertSame('listed', $candidate->status);
        $this->assertSame($candidate->id, $product->vendor_product_candidate_id);
    }

    public function test_each_variant_price_is_computed_from_its_own_cost_using_the_configured_margin(): void
    {
        AppSetting::set('pricing.profit_margin_percent', 80);

        $response = $this->postJson('/api/aliexpress/products', $this->payload([
            'variants' => [
                ['option_label' => 'Small', 'size' => 'S', 'cost_price' => 10],
                ['option_label' => 'Large', 'size' => 'L', 'cost_price' => 20],
            ],
        ]), [
            'Authorization' => 'Bearer test-extension-token',
        ]);

        $response->assertCreated();

        $product = Product::where('title', 'Wireless Earbuds')->firstOrFail();
        // 80% margin: min cost 10 -> product price 18.00; each variant priced off its own cost.
        $this->assertSame('18.00', (string) $product->price);
        $small = $product->variants->firstWhere('option_label', 'Small');
        $large = $product->variants->firstWhere('option_label', 'Large');
        $this->assertSame('18.00', (string) $small->price);
        $this->assertSame('36.00', (string) $large->price);
    }

    public function test_reimporting_the_same_external_id_does_not_duplicate_the_product(): void
    {
        $this->postJson('/api/aliexpress/products', $this->payload(), [
            'Authorization' => 'Bearer test-extension-token',
        ])->assertCreated();

        $response = $this->postJson('/api/aliexpress/products', $this->payload(), [
            'Authorization' => 'Bearer test-extension-token',
        ]);

        $response->assertOk()->assertJson(['status' => 'exists']);
        $this->assertSame(1, Product::where('title', 'Wireless Earbuds')->count());
    }

    public function test_it_requires_at_least_one_variant(): void
    {
        $response = $this->postJson('/api/aliexpress/products', $this->payload(['variants' => []]), [
            'Authorization' => 'Bearer test-extension-token',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('products', ['title' => 'Wireless Earbuds']);
    }

    /**
     * Regression test: ImageOptimizer::storeFromUrl() called a method
     * (ImageManager::read()) that doesn't exist on the installed
     * intervention/image version — it threw, was swallowed by the
     * blanket catch, and silently returned null every time. Every
     * AliExpress import therefore published with zero images despite the
     * scraper finding plenty, which is why real imports showed "No
     * Image" placeholders in the admin panel.
     */
    public function test_scraped_images_are_actually_downloaded_and_attached(): void
    {
        Storage::fake('public');

        $image = imagecreatetruecolor(1, 1);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        Http::fake(['*' => Http::response($bytes, 200, ['Content-Type' => 'image/png'])]);

        $response = $this->postJson('/api/aliexpress/products', $this->payload([
            'images' => ['https://ae-pic.example.com/photo1.jpg'],
            'variants' => [
                ['option_label' => 'Black', 'color' => 'Black', 'cost_price' => 8.50, 'image' => 'https://ae-pic.example.com/variant1.jpg'],
            ],
        ]), [
            'Authorization' => 'Bearer test-extension-token',
        ]);

        $response->assertCreated();

        $product = Product::where('title', 'Wireless Earbuds')->firstOrFail();
        $this->assertNotEmpty($product->images);
        $this->assertNotNull($product->variants->first()->image);
    }
}
