<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AliExpressManualPrefillTest extends TestCase
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

    private function tinyPngBytes(): string
    {
        $image = imagecreatetruecolor(1, 1);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    public function test_the_prefill_endpoint_downloads_and_rehosts_fetched_images(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response($this->tinyPngBytes(), 200, ['Content-Type' => 'image/png'])]);

        $response = $this->actingAs($this->admin())->postJson(route('admin.products.aliexpress-prefill'), [
            'title' => 'Compression Shirt',
            'description' => 'Quick-dry fabric.',
            'images' => ['https://ae-pic.example.com/photo1.jpg', 'https://ae-pic.example.com/photo2.jpg'],
            'variants' => [
                ['option_label' => 'Red', 'color' => 'Red', 'cost_price' => 5.5, 'image' => 'https://ae-pic.example.com/variant1.jpg'],
                ['option_label' => 'Blue', 'color' => 'Blue', 'cost_price' => 5.5, 'image' => null],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('title', 'Compression Shirt');
        $response->assertJsonCount(2, 'images');
        $response->assertJsonCount(2, 'variants');
        $response->assertJsonPath('variants.0.option_label', 'Red');
        $this->assertNotNull($response->json('variants.0.image'));
        $this->assertNull($response->json('variants.1.image'));
    }

    public function test_the_prefill_endpoint_requires_a_title(): void
    {
        $response = $this->actingAs($this->admin())->postJson(route('admin.products.aliexpress-prefill'), [
            'images' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_a_guest_cannot_use_the_prefill_endpoint(): void
    {
        $this->postJson(route('admin.products.aliexpress-prefill'), ['title' => 'X'])->assertUnauthorized();
    }

    public function test_manual_product_creation_accepts_a_source_url_and_pre_fetched_image_urls(): void
    {
        Storage::fake('public');

        $category = $this->category();

        $response = $this->actingAs($this->admin())->post(route('admin.products.store-manual'), [
            'title' => 'Compression Shirt',
            'description' => 'Quick-dry fabric.',
            'category_id' => $category->id,
            'price' => 19.99,
            'source_url' => 'https://www.aliexpress.com/item/1005010559613267.html',
            'images' => ['https://shobley.test/storage/products/already-hosted.webp'],
            'variants' => [
                [
                    'sku' => 'SHB-SHIRT-1',
                    'option_label' => 'Red',
                    'color' => 'Red',
                    'cost_price' => 5.5,
                    'vendor_image' => 'https://shobley.test/storage/products/variants/already-hosted.webp',
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.products'));

        $product = Product::where('title', 'Compression Shirt')->firstOrFail();
        $this->assertSame('https://www.aliexpress.com/item/1005010559613267.html', $product->source_url);
        $this->assertSame(['https://shobley.test/storage/products/already-hosted.webp'], $product->images);

        $variant = $product->variants()->firstOrFail();
        $this->assertSame('https://shobley.test/storage/products/variants/already-hosted.webp', $variant->image);
    }

    public function test_manual_product_creation_still_works_with_only_uploaded_images_and_no_source_url(): void
    {
        Storage::fake('public');

        $category = $this->category();

        $response = $this->actingAs($this->admin())->post(route('admin.products.store-manual'), [
            'title' => 'Hand-Poured Soy Candle',
            'category_id' => $category->id,
            'price' => 24.99,
            'custom_images' => [UploadedFile::fake()->image('candle.jpg')],
            'variants' => [
                ['sku' => 'SHB-CANDLE-1', 'option_label' => 'Lavender', 'cost_price' => 6.50],
            ],
        ]);

        $response->assertRedirect(route('admin.products'));

        $product = Product::where('title', 'Hand-Poured Soy Candle')->firstOrFail();
        $this->assertNull($product->source_url);
        $this->assertNotEmpty($product->images);
    }
}
