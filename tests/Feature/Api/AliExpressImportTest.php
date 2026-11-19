<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\VendorProductCandidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertSame('8.50', (string) $product->price);
        $this->assertCount(2, $product->variants);
        $this->assertTrue($product->variants->every(fn ($variant) => $variant->is_active));

        $candidate = VendorProductCandidate::where('vendor', 'aliexpress')
            ->where('vendor_external_id', '1005006123456789')
            ->firstOrFail();
        $this->assertSame('listed', $candidate->status);
        $this->assertSame($candidate->id, $product->vendor_product_candidate_id);
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
}
