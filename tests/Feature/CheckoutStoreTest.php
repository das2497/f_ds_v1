<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmedMail;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckoutStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_checkout_creates_an_order_with_its_items_and_moves_to_payment(): void
    {
        Mail::fake();

        $product = Product::create([
            'source_vendor' => 'printful',
            'title' => 'Checkout Test Tee',
            'slug' => 'checkout-test-tee',
            'price' => 20,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'CHECKOUT-TEST-SKU',
            'option_label' => 'M / Black',
            'cost_price' => 8,
            'price' => 20,
            'stock_status' => 'in_stock',
            'is_active' => true,
        ]);

        $cart = Cart::create(['session_id' => 'checkout-test-session']);
        CartItem::create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 2]);

        $response = $this->withCookie('cart_token', 'checkout-test-session')->post(route('checkout.store'), [
            'email' => 'customer@example.com',
            'shipping_name' => 'Jane Doe',
            'shipping_address_line1' => '123 Main St',
            'shipping_city' => 'Austin',
            'shipping_state' => 'TX',
            'shipping_zip' => '78701',
            'shipping_method' => 'standard',
        ]);

        $order = Order::firstOrFail();

        $response->assertRedirect(route('checkout.pay', $order));
        $this->assertSame('pending_payment', $order->status);
        $this->assertSame('customer@example.com', $order->email);
        $this->assertCount(1, $order->items);
        $this->assertSame(2, $order->items->first()->quantity);
        $this->assertSame('CHECKOUT-TEST-SKU', $order->items->first()->sku);
        Mail::assertQueued(OrderConfirmedMail::class);
    }
}
