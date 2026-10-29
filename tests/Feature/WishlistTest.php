<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_when_adding_to_wishlist(): void
    {
        $product = Product::create([
            'source_vendor' => 'printful',
            'title' => 'Wishlist Test Tee',
            'slug' => 'wishlist-test-tee',
            'price' => 20,
        ]);

        $response = $this->post(route('wishlist.store'), ['product_id' => $product->id]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_authenticated_user_can_add_a_product_to_their_wishlist(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'source_vendor' => 'printful',
            'title' => 'Wishlist Test Tee',
            'slug' => 'wishlist-test-tee',
            'price' => 20,
        ]);

        $response = $this->actingAs($user)->post(route('wishlist.store'), ['product_id' => $product->id]);

        $response->assertRedirect();
        $this->assertDatabaseHas('wishlists', ['user_id' => $user->id, 'product_id' => $product->id]);
    }

    public function test_adding_the_same_product_twice_does_not_duplicate_the_wishlist_entry(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'source_vendor' => 'printful',
            'title' => 'Wishlist Test Tee',
            'slug' => 'wishlist-test-tee',
            'price' => 20,
        ]);

        $this->actingAs($user)->post(route('wishlist.store'), ['product_id' => $product->id]);
        $this->actingAs($user)->post(route('wishlist.store'), ['product_id' => $product->id]);

        $this->assertDatabaseCount('wishlists', 1);
    }

    public function test_user_can_remove_a_product_from_their_wishlist(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'source_vendor' => 'printful',
            'title' => 'Wishlist Test Tee',
            'slug' => 'wishlist-test-tee',
            'price' => 20,
        ]);
        Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id]);

        $response = $this->actingAs($user)->delete(route('wishlist.destroy', $product));

        $response->assertRedirect();
        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_user_only_sees_their_own_wishlist(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $product = Product::create([
            'source_vendor' => 'printful',
            'title' => 'Wishlist Test Tee',
            'slug' => 'wishlist-test-tee',
            'price' => 20,
        ]);
        Wishlist::create(['user_id' => $otherUser->id, 'product_id' => $product->id]);

        $response = $this->actingAs($user)->get(route('wishlist.index'));

        $response->assertOk();
        $response->assertViewHas('products', fn ($products) => $products->isEmpty());
    }
}
