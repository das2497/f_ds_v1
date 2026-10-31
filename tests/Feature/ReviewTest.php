<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(): Product
    {
        return Product::create([
            'source_vendor' => 'printful',
            'title' => 'Review Test Tee',
            'slug' => 'review-test-tee',
            'price' => 20,
        ]);
    }

    private function makePaidOrderFor(User $user, Product $product): Order
    {
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'RTT-'.random_int(10000, 99999),
            'option_label' => 'Default',
            'cost_price' => 10,
            'price' => 20,
        ]);

        $order = Order::create([
            'order_number' => 'SHB-'.random_int(10000, 99999),
            'user_id' => $user->id,
            'email' => $user->email,
            'shipping_name' => 'Jane Doe',
            'shipping_address_line1' => '123 Main St',
            'shipping_city' => 'Austin',
            'shipping_state' => 'TX',
            'shipping_zip' => '78701',
            'shipping_country' => 'US',
            'shipping_method' => 'standard',
            'subtotal' => 20,
            'shipping_total' => 0,
            'tax_total' => 0,
            'total' => 20,
            'currency' => 'USD',
            'status' => 'paid',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'product_title' => $product->title,
            'sku' => $variant->sku,
            'unit_price' => 20,
            'unit_cost' => 10,
            'quantity' => 1,
            'line_total' => 20,
        ]);

        return $order;
    }

    public function test_guest_is_redirected_to_login_when_submitting_a_review(): void
    {
        $product = $this->makeProduct();

        $response = $this->post(route('reviews.store', $product), ['rating' => 5, 'body' => 'Great product!']);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_user_who_has_not_purchased_the_product_cannot_review_it(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct();

        $response = $this->actingAs($user)->post(route('reviews.store', $product), [
            'rating' => 5,
            'body' => 'Great product!',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_user_with_a_pending_payment_order_cannot_review_the_product(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct();
        $order = $this->makePaidOrderFor($user, $product);
        $order->update(['status' => 'pending_payment']);

        $response = $this->actingAs($user)->post(route('reviews.store', $product), [
            'rating' => 5,
            'body' => 'Great product!',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_user_who_purchased_the_product_can_submit_a_review(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct();
        $this->makePaidOrderFor($user, $product);

        $response = $this->actingAs($user)->post(route('reviews.store', $product), [
            'rating' => 4,
            'body' => 'Really happy with this purchase.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('reviews', [
            'product_id' => $product->id,
            'user_id' => $user->id,
            'rating' => 4,
            'body' => 'Really happy with this purchase.',
        ]);
    }

    public function test_review_requires_a_valid_rating_and_body(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct();
        $this->makePaidOrderFor($user, $product);

        $response = $this->actingAs($user)->post(route('reviews.store', $product), [
            'rating' => 6,
            'body' => '',
        ]);

        $response->assertSessionHasErrors(['rating', 'body']);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_user_cannot_review_the_same_product_twice(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct();
        $this->makePaidOrderFor($user, $product);
        Review::create(['product_id' => $product->id, 'user_id' => $user->id, 'rating' => 5, 'body' => 'First review.']);

        $response = $this->actingAs($user)->post(route('reviews.store', $product), [
            'rating' => 3,
            'body' => 'Trying again.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'You have already reviewed this product.');
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_product_page_shows_the_average_rating_and_reviews(): void
    {
        $product = $this->makeProduct();
        $reviewer = User::factory()->create(['name' => 'Alex Reviewer']);
        Review::create(['product_id' => $product->id, 'user_id' => $reviewer->id, 'rating' => 5, 'body' => 'Loved it!']);

        $response = $this->get(route('products.show', $product));

        $response->assertOk();
        $response->assertSee('Alex Reviewer');
        $response->assertSee('Loved it!');
    }
}
