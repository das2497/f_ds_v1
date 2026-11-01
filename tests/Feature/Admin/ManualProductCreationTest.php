<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualProductCreationTest extends TestCase
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

    public function test_an_admin_can_view_the_manual_product_form(): void
    {
        $this->category();

        $response = $this->actingAs($this->admin())->get(route('admin.products.create-manual'));

        $response->assertOk();
        $response->assertSee('Add Product Manually');
    }

    public function test_an_admin_can_publish_a_manually_listed_product(): void
    {
        Storage::fake('public');

        $category = $this->category();

        $response = $this->actingAs($this->admin())->post(route('admin.products.store-manual'), [
            'title' => 'Hand-Poured Soy Candle',
            'description' => 'A lovely candle.',
            'category_id' => $category->id,
            'price' => 24.99,
            'is_active' => '1',
            'custom_images' => [UploadedFile::fake()->image('candle.jpg')],
            'variants' => [
                [
                    'sku' => 'SHB-CANDLE-1',
                    'option_label' => 'Lavender',
                    'color' => 'Purple',
                    'cost_price' => 6.50,
                    'weight_lb' => 0.8,
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.products'));

        $product = Product::where('title', 'Hand-Poured Soy Candle')->firstOrFail();

        $this->assertSame('manual', $product->source_vendor);
        $this->assertNull($product->vendor_product_candidate_id);
        $this->assertTrue($product->is_active);
        $this->assertNotEmpty($product->images);

        $variant = $product->variants()->firstOrFail();
        $this->assertSame('SHB-CANDLE-1', $variant->sku);
        $this->assertSame('Lavender', $variant->option_label);
        $this->assertNull($variant->vendor_sku);
        $this->assertSame('in_stock', $variant->stock_status);
    }

    public function test_manual_product_creation_requires_at_least_one_variant(): void
    {
        $category = $this->category();

        $response = $this->actingAs($this->admin())->post(route('admin.products.store-manual'), [
            'title' => 'Hand-Poured Soy Candle',
            'category_id' => $category->id,
            'price' => 24.99,
            'variants' => [],
        ]);

        $response->assertSessionHasErrors('variants');
        $this->assertDatabaseMissing('products', ['title' => 'Hand-Poured Soy Candle']);
    }

    public function test_manual_product_creation_rejects_a_sku_already_in_use(): void
    {
        $category = $this->category();
        $existing = Product::create([
            'category_id' => $category->id,
            'source_vendor' => 'manual',
            'title' => 'Existing Product',
            'slug' => 'existing-product',
            'price' => 10,
            'images' => [],
            'is_active' => true,
        ]);
        $existing->variants()->create([
            'sku' => 'SHB-TAKEN',
            'option_label' => 'Default',
            'cost_price' => 5,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())->post(route('admin.products.store-manual'), [
            'title' => 'Hand-Poured Soy Candle',
            'category_id' => $category->id,
            'price' => 24.99,
            'variants' => [
                ['sku' => 'SHB-TAKEN', 'option_label' => 'Lavender', 'cost_price' => 6.50],
            ],
        ]);

        $response->assertSessionHasErrors('variants.0.sku');
        $this->assertDatabaseMissing('products', ['title' => 'Hand-Poured Soy Candle']);
    }

    public function test_a_non_admin_cannot_access_manual_product_creation(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $category = $this->category();

        $this->actingAs($user)->get(route('admin.products.create-manual'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.products.store-manual'), [
            'title' => 'Hand-Poured Soy Candle',
            'category_id' => $category->id,
            'price' => 24.99,
            'variants' => [['sku' => 'SHB-X', 'option_label' => 'Default', 'cost_price' => 5]],
        ])->assertForbidden();

        $this->assertDatabaseMissing('products', ['title' => 'Hand-Poured Soy Candle']);
    }

    public function test_a_guest_is_redirected_away_from_manual_product_creation(): void
    {
        $this->get(route('admin.products.create-manual'))->assertRedirect(route('login'));
        $this->post(route('admin.products.store-manual'), [])->assertRedirect(route('login'));
    }
}
