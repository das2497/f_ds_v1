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

class ProductImageVideoUploadTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function candidate(): VendorProductCandidate
    {
        return VendorProductCandidate::create([
            'vendor' => 'printful',
            'vendor_external_id' => '300',
            'raw_payload' => ['title' => 'Black Glossy Mug'],
            'title' => 'Black Glossy Mug',
            'base_cost' => 8.11,
            'preview_image_url' => 'https://files.cdn.printful.com/preview.jpg',
            'category_hint' => 'Mug',
            'variant_count' => 1,
            'status' => 'selected',
        ]);
    }

    public function test_store_merges_vendor_images_with_uploaded_images_and_video(): void
    {
        Storage::fake('public');

        $category = Category::create(['name' => 'Home & Living', 'slug' => 'home-living']);
        $candidate = $this->candidate();

        $response = $this->actingAs($this->admin())->post(route('admin.products.store'), [
            'candidate_id' => $candidate->id,
            'title' => 'Black Glossy Mug',
            'category_id' => $category->id,
            'price' => 20.28,
            'is_active' => '1',
            'images' => [
                'https://files.cdn.printful.com/one.jpg',
                'https://files.cdn.printful.com/two.jpg',
            ],
            'custom_images' => [
                UploadedFile::fake()->image('custom1.jpg'),
                UploadedFile::fake()->image('custom2.jpg'),
            ],
            'video' => UploadedFile::fake()->create('demo.mp4', 2048, 'video/mp4'),
            'variants' => [
                ['list' => '1', 'sku' => 'SHB-MUG-1', 'vendor_sku' => '9323', 'option_label' => 'Black / 11 oz', 'color' => 'Black', 'size' => '11 oz', 'cost_price' => 8.11, 'weight_lb' => null],
            ],
        ]);

        $response->assertRedirect(route('admin.products'));

        $product = Product::where('title', 'Black Glossy Mug')->firstOrFail();

        $this->assertCount(4, $product->images);
        $this->assertSame('https://files.cdn.printful.com/one.jpg', $product->images[0]);
        $this->assertNotNull($product->video_path);
        Storage::disk('public')->assertExists($product->video_path);
    }

    public function test_store_rejects_more_than_five_images(): void
    {
        Storage::fake('public');

        $category = Category::create(['name' => 'Home & Living', 'slug' => 'home-living']);
        $candidate = $this->candidate();

        $response = $this->actingAs($this->admin())->post(route('admin.products.store'), [
            'candidate_id' => $candidate->id,
            'title' => 'Black Glossy Mug',
            'category_id' => $category->id,
            'price' => 20.28,
            'images' => [
                'https://files.cdn.printful.com/one.jpg',
                'https://files.cdn.printful.com/two.jpg',
                'https://files.cdn.printful.com/three.jpg',
                'https://files.cdn.printful.com/four.jpg',
            ],
            'custom_images' => [
                UploadedFile::fake()->image('custom1.jpg'),
                UploadedFile::fake()->image('custom2.jpg'),
            ],
            'variants' => [
                ['list' => '1', 'sku' => 'SHB-MUG-1', 'vendor_sku' => '9323', 'option_label' => 'Black / 11 oz', 'color' => 'Black', 'size' => '11 oz', 'cost_price' => 8.11, 'weight_lb' => null],
            ],
        ]);

        $response->assertSessionHasErrors('images');
        $this->assertDatabaseMissing('products', ['title' => 'Black Glossy Mug']);
    }

    public function test_update_can_replace_video_and_change_kept_images(): void
    {
        Storage::fake('public');

        $category = Category::create(['name' => 'Home & Living', 'slug' => 'home-living']);
        $oldVideoPath = UploadedFile::fake()->create('old.mp4', 1024, 'video/mp4')->store('products/videos', 'public');

        $product = Product::create([
            'category_id' => $category->id,
            'title' => 'Black Glossy Mug',
            'slug' => 'black-glossy-mug',
            'price' => 20.28,
            'images' => ['https://files.cdn.printful.com/one.jpg', 'https://files.cdn.printful.com/two.jpg'],
            'video_path' => $oldVideoPath,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())->put(route('admin.products.update', $product), [
            'title' => 'Black Glossy Mug',
            'category_id' => $category->id,
            'price' => 21.00,
            'is_active' => '1',
            'keep_images' => ['https://files.cdn.printful.com/one.jpg'],
            'custom_images' => [UploadedFile::fake()->image('new.jpg')],
            'remove_video' => '1',
        ]);

        $response->assertRedirect();

        $product->refresh();

        $this->assertCount(2, $product->images);
        $this->assertSame('https://files.cdn.printful.com/one.jpg', $product->images[0]);
        $this->assertNull($product->video_path);
        Storage::disk('public')->assertMissing($oldVideoPath);
    }
}
