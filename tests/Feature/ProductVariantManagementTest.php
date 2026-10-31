<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProductCandidate;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductVariantManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function category(): Category
    {
        return Category::create(['name' => 'Home & Living', 'slug' => 'home-living']);
    }

    private function candidate(): VendorProductCandidate
    {
        return VendorProductCandidate::create([
            'vendor' => 'printful',
            'vendor_external_id' => '605',
            'raw_payload' => [
                'variants' => [
                    ['vendor_sku' => '15524', 'option_label' => 'Black / AirPods Pro', 'color' => 'Black', 'size' => 'AirPods Pro', 'cost_price' => 10.35, 'weight_lb' => null, 'image' => 'https://files.cdn.printful.com/black.jpg'],
                    ['vendor_sku' => '15534', 'option_label' => 'Blue / AirPods Pro', 'color' => 'Blue', 'size' => 'AirPods Pro', 'cost_price' => 10.35, 'weight_lb' => null, 'image' => 'https://files.cdn.printful.com/blue.jpg'],
                ],
            ],
            'title' => 'Rubber Case for AirPods',
            'base_cost' => 10.35,
            'preview_image_url' => 'https://files.cdn.printful.com/preview.jpg',
            'category_hint' => 'Case',
            'variant_count' => 2,
            'status' => 'selected',
        ]);
    }

    public function test_store_uses_uploaded_variant_image_over_vendor_image(): void
    {
        Storage::fake('public');

        $category = $this->category();
        $candidate = $this->candidate();

        $response = $this->actingAs($this->admin())->post(route('admin.products.store'), [
            'candidate_id' => $candidate->id,
            'title' => 'Rubber Case for AirPods',
            'category_id' => $category->id,
            'price' => 25.88,
            'variants' => [
                [
                    'list' => '1',
                    'sku' => 'SHB-CASE-1',
                    'vendor_sku' => '15524',
                    'option_label' => 'Black / AirPods Pro',
                    'color' => 'Black',
                    'size' => 'AirPods Pro',
                    'cost_price' => 10.35,
                    'weight_lb' => null,
                    'vendor_image' => 'https://files.cdn.printful.com/black.jpg',
                    'image' => UploadedFile::fake()->image('custom-black.jpg'),
                ],
                [
                    'list' => '1',
                    'sku' => 'SHB-CASE-2',
                    'vendor_sku' => '15534',
                    'option_label' => 'Blue / AirPods Pro',
                    'color' => 'Blue',
                    'size' => 'AirPods Pro',
                    'cost_price' => 10.35,
                    'weight_lb' => null,
                    'vendor_image' => 'https://files.cdn.printful.com/blue.jpg',
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.products'));

        $product = Product::where('title', 'Rubber Case for AirPods')->firstOrFail();
        $black = $product->variants()->where('sku', 'SHB-CASE-1')->firstOrFail();
        $blue = $product->variants()->where('sku', 'SHB-CASE-2')->firstOrFail();

        $this->assertSame('15524', $black->vendor_sku);
        $this->assertSame('15534', $blue->vendor_sku);
        $this->assertStringContainsString('/storage/products/variants/', $black->image);
        $this->assertSame('https://files.cdn.printful.com/blue.jpg', $blue->image);
    }

    public function test_update_edits_variant_fields_image_and_active_state(): void
    {
        Storage::fake('public');

        $category = $this->category();
        $product = Product::create([
            'category_id' => $category->id,
            'title' => 'Rubber Case for AirPods',
            'slug' => 'rubber-case-for-airpods',
            'price' => 25.88,
            'images' => [],
            'is_active' => true,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'SHB-CASE-1',
            'vendor_sku' => '15524',
            'option_label' => 'Black / AirPods Pro',
            'image' => 'https://files.cdn.printful.com/black.jpg',
            'color' => 'Black',
            'size' => 'AirPods Pro',
            'cost_price' => 10.35,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())->put(route('admin.products.update', $product), [
            'title' => 'Rubber Case for AirPods',
            'category_id' => $category->id,
            'price' => 25.88,
            'existing_variants' => [
                $variant->id => [
                    'option_label' => 'Jet Black / AirPods Pro',
                    'color' => 'Jet Black',
                    'size' => 'AirPods Pro',
                    'sku' => 'SHB-CASE-1-RENAMED',
                    'weight_lb' => 0.05,
                    'image' => UploadedFile::fake()->image('replacement.jpg'),
                ],
            ],
        ]);

        $response->assertRedirect();

        $variant->refresh();

        $this->assertSame('Jet Black / AirPods Pro', $variant->option_label);
        $this->assertSame('Jet Black', $variant->color);
        $this->assertSame('SHB-CASE-1-RENAMED', $variant->sku);
        $this->assertFalse((bool) $variant->is_active);
        $this->assertStringContainsString('/storage/products/variants/', $variant->image);
    }

    public function test_update_can_point_an_existing_variant_at_a_different_picked_vendor_image(): void
    {
        $category = $this->category();
        $product = Product::create([
            'category_id' => $category->id,
            'title' => 'Rubber Case for AirPods',
            'slug' => 'rubber-case-for-airpods',
            'price' => 25.88,
            'images' => [],
            'is_active' => true,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'SHB-CASE-1',
            'vendor_sku' => '15524',
            'option_label' => 'Black / AirPods Pro',
            'image' => 'https://files.cdn.printful.com/black.jpg',
            'color' => 'Black',
            'size' => 'AirPods Pro',
            'cost_price' => 10.35,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())->put(route('admin.products.update', $product), [
            'title' => 'Rubber Case for AirPods',
            'category_id' => $category->id,
            'price' => 25.88,
            'existing_variants' => [
                $variant->id => [
                    'option_label' => 'Black / AirPods Pro',
                    'color' => 'Black',
                    'size' => 'AirPods Pro',
                    'sku' => 'SHB-CASE-1',
                    'is_active' => '1',
                    'vendor_image' => 'https://files.cdn.printful.com/blue.jpg',
                ],
            ],
        ]);

        $response->assertRedirect();

        $this->assertSame('https://files.cdn.printful.com/blue.jpg', $variant->fresh()->image);
    }

    public function test_the_edit_page_renders_a_vendor_image_picker_for_the_products_candidate_photos(): void
    {
        $category = $this->category();
        $candidate = $this->candidate();

        $product = Product::create([
            'category_id' => $category->id,
            'vendor_product_candidate_id' => $candidate->id,
            'title' => 'Rubber Case for AirPods',
            'slug' => 'rubber-case-for-airpods',
            'price' => 25.88,
            'images' => [],
            'is_active' => true,
        ]);
        $product->variants()->create([
            'sku' => 'SHB-CASE-1',
            'vendor_sku' => '15524',
            'option_label' => 'Black / AirPods Pro',
            'color' => 'Black',
            'size' => 'AirPods Pro',
            'cost_price' => 10.35,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.products.edit', $product));

        $response->assertOk();
        $response->assertSee('chooseVendorImageModal', false);
        $response->assertSee('js-choose-vendor-image', false);
        $response->assertSee('https://files.cdn.printful.com/black.jpg', false);
        $response->assertSee('https://files.cdn.printful.com/blue.jpg', false);
    }

    public function test_the_image_picker_also_includes_photos_already_uploaded_to_the_products_own_gallery(): void
    {
        $category = $this->category();
        $product = Product::create([
            'category_id' => $category->id,
            'title' => 'Rubber Case for AirPods',
            'slug' => 'rubber-case-for-airpods',
            'price' => 25.88,
            // Not vendor photos — e.g. a custom shot the admin uploaded
            // earlier via "Upload More Images" and kept in the gallery.
            'images' => ['https://cdn.example.com/custom-upload.jpg'],
            'is_active' => true,
        ]);
        $product->variants()->create([
            'sku' => 'SHB-CASE-1',
            'option_label' => 'Black / AirPods Pro',
            'cost_price' => 10.35,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.products.edit', $product));

        $response->assertOk();
        $response->assertSee('https://cdn.example.com/custom-upload.jpg', false);
    }

    public function test_update_can_add_a_previously_unselected_vendor_variant(): void
    {
        Storage::fake('public');

        $category = $this->category();
        $candidate = $this->candidate();

        $product = Product::create([
            'category_id' => $category->id,
            'vendor_product_candidate_id' => $candidate->id,
            'title' => 'Rubber Case for AirPods',
            'slug' => 'rubber-case-for-airpods',
            'price' => 25.88,
            'images' => [],
            'is_active' => true,
        ]);
        $product->variants()->create([
            'sku' => 'SHB-CASE-1',
            'vendor_sku' => '15524',
            'option_label' => 'Black / AirPods Pro',
            'color' => 'Black',
            'size' => 'AirPods Pro',
            'cost_price' => 10.35,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.products.edit', $product));
        $response->assertOk();
        $response->assertSee('15534');

        $updateResponse = $this->actingAs($this->admin())->put(route('admin.products.update', $product), [
            'title' => 'Rubber Case for AirPods',
            'category_id' => $category->id,
            'price' => 25.88,
            'new_variants' => [
                0 => [
                    'add' => '1',
                    'vendor_sku' => '15534',
                    'vendor_image' => 'https://files.cdn.printful.com/blue.jpg',
                    'option_label' => 'Blue / AirPods Pro',
                    'color' => 'Blue',
                    'size' => 'AirPods Pro',
                    'cost_price' => 10.35,
                    'sku' => 'SHB-CASE-2',
                ],
            ],
        ]);

        $updateResponse->assertRedirect();

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'sku' => 'SHB-CASE-2',
            'vendor_sku' => '15534',
            'image' => 'https://files.cdn.printful.com/blue.jpg',
        ]);
    }

    public function test_update_rejects_duplicate_sku_across_variants(): void
    {
        $category = $this->category();

        $existingProduct = Product::create([
            'category_id' => $category->id,
            'title' => 'Other Product',
            'slug' => 'other-product',
            'price' => 9.99,
            'images' => [],
            'is_active' => true,
        ]);
        $existingProduct->variants()->create([
            'sku' => 'SHB-TAKEN',
            'option_label' => 'Default',
            'cost_price' => 5,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'title' => 'Rubber Case for AirPods',
            'slug' => 'rubber-case-for-airpods',
            'price' => 25.88,
            'images' => [],
            'is_active' => true,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'SHB-CASE-1',
            'option_label' => 'Black / AirPods Pro',
            'cost_price' => 10.35,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())->put(route('admin.products.update', $product), [
            'title' => 'Rubber Case for AirPods',
            'category_id' => $category->id,
            'price' => 25.88,
            'existing_variants' => [
                $variant->id => [
                    'option_label' => 'Black / AirPods Pro',
                    'sku' => 'SHB-TAKEN',
                ],
            ],
        ]);

        $response->assertSessionHasErrors('variants');
        $this->assertSame('SHB-CASE-1', $variant->fresh()->sku);
    }

    public function test_store_sanitizes_rich_text_description_before_saving(): void
    {
        $category = $this->category();
        $candidate = $this->candidate();

        $response = $this->actingAs($this->admin())->post(route('admin.products.store'), [
            'candidate_id' => $candidate->id,
            'title' => 'Rubber Case for AirPods',
            'description' => '<p onclick="alert(1)">Soft <strong>silicone</strong> case.</p><script>alert(1)</script>',
            'category_id' => $category->id,
            'price' => 25.88,
            'variants' => [
                [
                    'list' => '1',
                    'sku' => 'SHB-CASE-1',
                    'vendor_sku' => '15524',
                    'option_label' => 'Black / AirPods Pro',
                    'color' => 'Black',
                    'size' => 'AirPods Pro',
                    'cost_price' => 10.35,
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.products'));

        $product = Product::where('title', 'Rubber Case for AirPods')->firstOrFail();

        $this->assertSame('<p>Soft <strong>silicone</strong> case.</p>', $product->description);
    }

    public function test_update_sanitizes_rich_text_description_before_saving(): void
    {
        $category = $this->category();
        $product = Product::create([
            'category_id' => $category->id,
            'title' => 'Rubber Case for AirPods',
            'slug' => 'rubber-case-for-airpods',
            'price' => 25.88,
            'images' => [],
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())->put(route('admin.products.update', $product), [
            'title' => 'Rubber Case for AirPods',
            'description' => '<p>Soft <strong>silicone</strong> case.</p><script>alert(1)</script>',
            'category_id' => $category->id,
            'price' => 25.88,
        ]);

        $response->assertRedirect();

        $this->assertSame('<p>Soft <strong>silicone</strong> case.</p>', $product->fresh()->description);
    }
}
