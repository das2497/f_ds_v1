<?php

namespace Tests\Feature;

use App\Models\AiProductScore;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorProductCandidate;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductScoreLinkingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_publishing_a_scored_candidate_links_its_ai_score_to_the_new_product(): void
    {
        $category = Category::create(['name' => 'Home & Living', 'slug' => 'home-living']);
        $candidate = VendorProductCandidate::create([
            'vendor' => 'cj',
            'vendor_external_id' => '605',
            'raw_payload' => ['variants' => [
                ['vendor_sku' => '1', 'option_label' => 'Default', 'cost_price' => 10.35, 'image' => null],
            ]],
            'title' => 'Rubber Case',
            'base_cost' => 10.35,
            'category_hint' => 'Case',
            'variant_count' => 1,
            'status' => 'selected',
        ]);

        $score = AiProductScore::create([
            'vendor' => 'cj', 'vendor_external_id' => '605', 'score' => 8,
            'verdict' => 'Great pick.', 'reasons' => ['Cheap'], 'risks' => [],
            'input_hash' => 'abc', 'scored_at' => now(),
        ]);

        $response = $this->actingAs($this->admin())->post(route('admin.products.store'), [
            'candidate_id' => $candidate->id,
            'title' => 'Rubber Case',
            'category_id' => $category->id,
            'price' => 25.88,
            'variants' => [
                ['list' => '1', 'sku' => 'SHB-CASE-1', 'vendor_sku' => '1', 'option_label' => 'Default', 'cost_price' => 10.35],
            ],
        ]);

        $response->assertRedirect(route('admin.products'));

        $product = Product::where('title', 'Rubber Case')->firstOrFail();

        $this->assertSame($product->id, $score->fresh()->product_id);
        $this->assertTrue($product->aiScore()->exists());
        $this->assertSame(8, $product->aiScore->score);
    }

    public function test_a_product_with_no_scored_candidate_has_no_ai_score(): void
    {
        $category = Category::create(['name' => 'Home & Living', 'slug' => 'home-living']);
        $product = Product::create([
            'category_id' => $category->id,
            'title' => 'Unscored Product',
            'slug' => 'unscored-product',
            'price' => 10,
            'images' => [],
            'is_active' => true,
        ]);

        $this->assertNull($product->aiScore);
    }
}
