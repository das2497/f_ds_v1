<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartAjaxUpdateTest extends TestCase
{
    use RefreshDatabase;

    private ?Cart $cart = null;

    private function category(): Category
    {
        return Category::firstOrCreate(['slug' => 'electronics'], ['name' => 'Electronics']);
    }

    private function cartItem(array $variantAttributes = [], float $price = 10.0): CartItem
    {
        if (! $this->cart) {
            $user = User::factory()->create();
            $this->cart = Cart::create(['user_id' => $user->id]);
            $this->actingAs($user);
        }

        $slug = 'widget-'.$this->cart->items()->count();
        $product = Product::create([
            'category_id' => $this->category()->id,
            'title' => 'Widget',
            'slug' => $slug,
            'price' => $price,
            'images' => [],
            'is_active' => true,
        ]);
        $variant = $product->variants()->create(array_merge([
            'sku' => 'SHB-'.$slug, 'option_label' => 'Default', 'cost_price' => 5,
            'stock_status' => 'in_stock', 'is_active' => true,
        ], $variantAttributes));

        return $this->cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);
    }

    public function test_updating_quantity_via_ajax_returns_updated_totals_without_a_redirect(): void
    {
        $item = $this->cartItem([], price: 10.0);

        $response = $this->putJson(route('cart.update', $item), ['quantity' => 3]);

        $response->assertOk();
        $response->assertJson([
            'item' => ['id' => $item->id, 'quantity' => 3, 'line_total' => '30.00', 'unit_price' => '10.00'],
            'subtotal' => '30.00',
            'cart_count' => 3,
            'has_unavailable_items' => false,
        ]);
    }

    public function test_removing_an_item_via_ajax_returns_updated_totals_without_a_redirect(): void
    {
        $item = $this->cartItem([], price: 10.0);

        $response = $this->deleteJson(route('cart.destroy', $item));

        $response->assertOk();
        $response->assertJson(['subtotal' => '0.00', 'cart_count' => 0, 'has_unavailable_items' => false]);
        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_an_out_of_stock_item_still_marks_the_cart_as_having_unavailable_items_after_an_ajax_update(): void
    {
        $outOfStock = $this->cartItem(['stock_status' => 'out_of_stock'], price: 10.0);
        // A second, available item so the cart isn't emptied by this update.
        $this->cartItem([], price: 5.0);

        $response = $this->putJson(route('cart.update', $outOfStock), ['quantity' => 2]);

        $response->assertOk();
        $response->assertJsonPath('has_unavailable_items', true);
    }

    public function test_updating_quantity_without_ajax_headers_still_redirects_back(): void
    {
        $item = $this->cartItem([], price: 10.0);

        $response = $this->put(route('cart.update', $item), ['quantity' => 2]);

        $response->assertRedirect();
    }
}
