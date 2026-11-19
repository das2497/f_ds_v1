<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Subcategory;
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

    public function test_editing_a_product_row_with_a_valid_category_and_subcategory_resolves_them_by_name(): void
    {
        $product = $this->product();
        $category = Category::create(['name' => 'Toys & Games', 'slug' => 'toys-games']);
        $subcategory = Subcategory::create(['category_id' => $category->id, 'name' => 'Outdoor Play', 'slug' => 'outdoor-play']);

        $response = $this->postJson('/api/sheet-sync/webhook', [
            'sheet' => 'Products',
            'id' => $product->id,
            'values' => [
                'Title' => 'Original Title',
                'Price' => '10',
                'Active' => 'TRUE',
                // Case-insensitive on purpose — an admin typing into a
                // sheet cell shouldn't have to match capitalization.
                'Category' => 'toys & games',
                'Subcategory' => 'outdoor play',
            ],
        ], ['Authorization' => 'Bearer test-sheet-token']);

        $response->assertOk()->assertJson(['status' => 'ok']);

        $product->refresh();
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($subcategory->id, $product->subcategory_id);
    }

    public function test_an_unknown_category_name_is_rejected_without_changing_the_product(): void
    {
        $product = $this->product();

        $response = $this->postJson('/api/sheet-sync/webhook', [
            'sheet' => 'Products',
            'id' => $product->id,
            'values' => [
                'Title' => 'Original Title',
                'Price' => '10',
                'Active' => 'TRUE',
                'Category' => 'Not A Real Category',
            ],
        ], ['Authorization' => 'Bearer test-sheet-token']);

        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
        $this->assertNull($product->fresh()->category_id);
    }

    public function test_clearing_the_category_cell_clears_the_products_category(): void
    {
        $category = Category::create(['name' => 'Toys & Games', 'slug' => 'toys-games']);
        $product = $this->product(['category_id' => $category->id]);

        $response = $this->postJson('/api/sheet-sync/webhook', [
            'sheet' => 'Products',
            'id' => $product->id,
            'values' => [
                'Title' => 'Original Title',
                'Price' => '10',
                'Active' => 'TRUE',
                'Category' => '',
            ],
        ], ['Authorization' => 'Bearer test-sheet-token']);

        $response->assertOk();
        $product->refresh();
        $this->assertNull($product->category_id);
        $this->assertNull($product->subcategory_id);
    }
}
