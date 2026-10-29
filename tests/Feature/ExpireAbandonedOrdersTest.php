<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireAbandonedOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(string $status, \DateTimeInterface $createdAt): Order
    {
        $order = Order::create([
            'order_number' => 'SHB-'.random_int(10000, 99999),
            'email' => 'customer@example.com',
            'shipping_name' => 'Jane Doe',
            'shipping_address_line1' => '123 Main St',
            'shipping_city' => 'Austin',
            'shipping_state' => 'TX',
            'shipping_zip' => '78701',
            'shipping_country' => 'US',
            'shipping_method' => 'standard',
            'subtotal' => 50,
            'shipping_total' => 8.5,
            'tax_total' => 4,
            'total' => 62.5,
            'currency' => 'USD',
            'status' => $status,
        ]);

        $order->forceFill(['created_at' => $createdAt])->save();

        return $order;
    }

    public function test_it_cancels_pending_payment_orders_past_the_grace_window(): void
    {
        $stale = $this->makeOrder('pending_payment', now()->subHours(25));

        $this->artisan('app:expire-abandoned-orders')->assertSuccessful();

        $this->assertSame('cancelled', $stale->refresh()->status);
        $this->assertNotNull($stale->cancelled_at);
    }

    public function test_it_leaves_recent_pending_payment_orders_alone(): void
    {
        $fresh = $this->makeOrder('pending_payment', now()->subHours(1));

        $this->artisan('app:expire-abandoned-orders')->assertSuccessful();

        $this->assertSame('pending_payment', $fresh->refresh()->status);
    }

    public function test_it_never_touches_orders_that_already_moved_on(): void
    {
        $paid = $this->makeOrder('paid', now()->subHours(48));

        $this->artisan('app:expire-abandoned-orders')->assertSuccessful();

        $this->assertSame('paid', $paid->refresh()->status);
    }
}
