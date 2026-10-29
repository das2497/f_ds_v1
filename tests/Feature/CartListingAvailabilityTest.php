<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartListingAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function cartWithVariant(array $variantAttributes): Cart
    {
        $user = User::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);

        $category = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);
        $product = Product::create([
            'category_id' => $category->id,
            'title' => 'Widget',
            'slug' => 'widget',
            'price' => 12.5,
            'images' => [],
            'is_active' => true,
        ]);
        $variant = $product->variants()->create(array_merge([
            'sku' => 'SHB-W', 'option_label' => 'Default', 'cost_price' => 5, 'is_active' => true,
        ], $variantAttributes));

        $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

        $this->actingAs($user);

        return $cart;
    }

    public function test_an_out_of_stock_cart_item_shows_a_warning_and_disables_checkout(): void
    {
        $this->cartWithVariant(['stock_status' => 'out_of_stock']);

        $response = $this->get(route('cart.index'));

        $response->assertOk();
        $response->assertSee('No longer available', false);
        // Both the disabled button and the real checkout link are always in
        // the markup (so JS can toggle them after an AJAX update without a
        // re-render) — what must differ is which one is hidden.
        $response->assertSee('id="checkoutDisabledBtn" class="btn btn-accent w-100" style="" disabled', false);
        $response->assertSee('id="checkoutLink" class="btn btn-accent w-100" style="display:none;"', false);
    }

    public function test_an_in_stock_cart_item_shows_no_warning_and_allows_checkout(): void
    {
        $this->cartWithVariant(['stock_status' => 'in_stock']);

        $response = $this->get(route('cart.index'));

        $response->assertOk();
        $response->assertDontSee('No longer available', false);
        $response->assertSee('id="checkoutDisabledBtn" class="btn btn-accent w-100" style="display:none;" disabled', false);
        $response->assertSee('id="checkoutLink" class="btn btn-accent w-100" style=""', false);
    }
}
