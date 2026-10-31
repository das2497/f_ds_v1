<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReviewManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function makeProduct(string $title = 'Admin Review Test Tee'): Product
    {
        return Product::create([
            'source_vendor' => 'printful',
            'title' => $title,
            'slug' => Str::slug($title).'-'.random_int(1000, 9999),
            'price' => 20,
        ]);
    }

    public function test_guest_cannot_view_the_admin_reviews_page(): void
    {
        $response = $this->get(route('admin.reviews'));

        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_view_the_admin_reviews_page(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.reviews'));

        $response->assertForbidden();
    }

    public function test_admin_can_view_the_reviews_list(): void
    {
        $product = $this->makeProduct();
        $reviewer = User::factory()->create(['name' => 'Jordan Reviewer']);
        Review::create(['product_id' => $product->id, 'user_id' => $reviewer->id, 'rating' => 4, 'body' => 'Pretty good quality.']);

        $response = $this->actingAs($this->admin())->get(route('admin.reviews'));

        $response->assertOk();
        $response->assertSee('Jordan Reviewer');
        $response->assertSee('Pretty good quality.');
    }

    public function test_admin_can_filter_reviews_by_rating(): void
    {
        $product = $this->makeProduct();
        $reviewer = User::factory()->create();
        Review::create(['product_id' => $product->id, 'user_id' => $reviewer->id, 'rating' => 2, 'body' => 'Not great.']);
        Review::create(['product_id' => $this->makeProduct('Another Product')->id, 'user_id' => $reviewer->id, 'rating' => 5, 'body' => 'Excellent!']);

        $response = $this->actingAs($this->admin())->get(route('admin.reviews', ['rating' => 5]));

        $response->assertOk();
        $response->assertSee('Excellent!');
        $response->assertDontSee('Not great.');
    }

    public function test_admin_can_delete_a_review(): void
    {
        $product = $this->makeProduct();
        $reviewer = User::factory()->create();
        $review = Review::create(['product_id' => $product->id, 'user_id' => $reviewer->id, 'rating' => 1, 'body' => 'Inappropriate content.']);

        $response = $this->actingAs($this->admin())->delete(route('admin.reviews.destroy', $review));

        $response->assertRedirect();
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }
}
