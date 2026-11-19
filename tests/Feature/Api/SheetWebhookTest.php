<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SheetWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google_sheets.sync_token' => 'test-sheet-token']);
    }

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'source_vendor' => 'manual',
            'title' => 'Original Title',
            'slug' => 'original-title',
            'price' => 10,
            'images' => [],
            'is_active' => false,
        ], $overrides));
    }

    private function variant(Product $product, array $overrides = []): ProductVariant
    {
        return $product->variants()->create(array_merge([
            'sku' => 'SKU-1',
            'option_label' => 'Default',
            'cost_price' => 5,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ], $overrides));
    }

    public function test_it_rejects_a_request_without_a_valid_token(): void
    {
        $product = $this->product();

        $this->postJson('/api/sheet-sync/webhook', [
            'sheet' => 'Products',
            'id' => $product->id,
            'values' => ['Title' => 'Hacked', 'Price' => '1', 'Active' => 'TRUE'],
        ])->assertUnauthorized();

        $this->postJson('/api/sheet-sync/webhook', [
            'sheet' => 'Products',
            'id' => $product->id,
            'values' => ['Title' => 'Hacked', 'Price' => '1', 'Active' => 'TRUE'],
        ], ['Authorization' => 'Bearer wrong-token'])->assertUnauthorized();

        $this->assertSame('Original Title', $product->fresh()->title);
    }

    public function test_editing_a_product_row_updates_the_product(): void
    {
        $product = $this->product();

        $response = $this->postJson('/api/sheet-sync/webhook', [
            'sheet' => 'Products',
            'id' => $product->id,
            'values' => [
                'ID' => $product->id,
                'Title' => 'Updated Title',
                'Description' => 'New description',
                'Price' => '19.99',
                'Compare At Price' => '',
                'Active' => 'TRUE',
                'Source Vendor' => 'manual',
                'Updated At' => '',
                'Sync Status' => '',
            ],
        ], ['Authorization' => 'Bearer test-sheet-token']);

        $response->assertOk()->assertJson(['status' => 'ok']);

        $product->refresh();
        $this->assertSame('Updated Title', $product->title);
        $this->assertSame('19.99', (string) $product->price);
        $this->assertTrue($product->is_active);
    }

    public function test_editing_a_variant_row_updates_the_variant(): void
    {
        $product = $this->product();
        $variant = $this->variant($product);

        $response = $this->postJson('/api/sheet-sync/webhook', [
            'sheet' => 'Variants',
            'id' => $variant->id,
            'values' => [
                'ID' => $variant->id,
                'Product ID' => $product->id,
                'SKU' => $variant->sku,
                'Option Label' => $variant->option_label,
                'Color' => '',
                'Size' => '',
                'Cost Price' => '7.50',
                'Price' => '15.00',
                'Stock Status' => 'out_of_stock',
                'Active' => 'FALSE',
                'Updated At' => '',
                'Sync Status' => '',
            ],
        ], ['Authorization' => 'Bearer test-sheet-token']);

        $response->assertOk()->assertJson(['status' => 'ok']);

        $variant->refresh();
        $this->assertSame('7.50', (string) $variant->cost_price);
        $this->assertSame('15.00', (string) $variant->price);
        $this->assertSame('out_of_stock', $variant->stock_status);
        $this->assertFalse($variant->is_active);
    }

    public function test_an_unknown_id_returns_a_404_instead_of_creating_a_row(): void
    {
        $response = $this->postJson('/api/sheet-sync/webhook', [
            'sheet' => 'Products',
            'id' => 999999,
            'values' => ['Title' => 'Ghost', 'Price' => '1', 'Active' => 'TRUE'],
        ], ['Authorization' => 'Bearer test-sheet-token']);

        $response->assertNotFound();
        $this->assertDatabaseMissing('products', ['title' => 'Ghost']);
    }

    public function test_invalid_values_are_rejected_with_a_sheet_friendly_error(): void
    {
        $product = $this->product();

        $response = $this->postJson('/api/sheet-sync/webhook', [
            'sheet' => 'Products',
            'id' => $product->id,
            'values' => ['Title' => 'Updated', 'Price' => 'not-a-number', 'Active' => 'TRUE'],
        ], ['Authorization' => 'Bearer test-sheet-token']);

        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
        $this->assertSame('Original Title', $product->fresh()->title);
    }
}
