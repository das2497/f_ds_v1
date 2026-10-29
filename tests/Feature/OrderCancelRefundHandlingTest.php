<?php

namespace Tests\Feature;

use App\Exceptions\VendorApiException;
use App\Mail\OrderCancelledMail;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Services\Payment\StripeRefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderCancelRefundHandlingTest extends TestCase
{
    use RefreshDatabase;

    private function makeCancellableOrder(User $user): Order
    {
        return Order::create([
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
            'subtotal' => 50,
            'shipping_total' => 8.5,
            'tax_total' => 4,
            'total' => 62.5,
            'currency' => 'USD',
            'status' => 'paid',
            'release_at' => now()->addHour(),
        ]);
    }

    public function test_cancellation_email_and_refund_tracking_only_fire_when_refund_succeeds(): void
    {
        Mail::fake();
        Queue::fake();

        $user = User::factory()->create();
        $order = $this->makeCancellableOrder($user);

        $this->mock(StripeRefundService::class, function ($mock) use ($order) {
            $mock->shouldReceive('refund')
                ->once()
                ->andReturn(new Refund(['order_id' => $order->id, 'amount' => $order->total, 'status' => 'succeeded']));
        });

        $response = $this->actingAs($user)->post(route('orders.cancel.destroy', $order));

        $response->assertRedirect(route('order-success', $order));
        $response->assertSessionHas('status', 'Order cancelled and refunded.');

        $this->assertSame('cancelled', $order->refresh()->status);
        Mail::assertQueued(OrderCancelledMail::class);
    }

    public function test_a_refund_that_comes_back_failed_cancels_the_order_without_claiming_it_was_refunded(): void
    {
        Mail::fake();
        Queue::fake();

        $user = User::factory()->create();
        $order = $this->makeCancellableOrder($user);

        $this->mock(StripeRefundService::class, function ($mock) use ($order) {
            $mock->shouldReceive('refund')
                ->once()
                ->andReturn(new Refund(['order_id' => $order->id, 'amount' => $order->total, 'status' => 'failed']));
        });

        $response = $this->actingAs($user)->post(route('orders.cancel.destroy', $order));

        $response->assertRedirect(route('order-success', $order));
        $response->assertSessionHas('status', fn ($status) => str_contains($status, 'manually'));

        $this->assertSame('cancelled', $order->refresh()->status);
        Mail::assertNotQueued(OrderCancelledMail::class);
    }

    public function test_a_refund_that_throws_does_not_crash_the_request(): void
    {
        Mail::fake();
        Queue::fake();

        $user = User::factory()->create();
        $order = $this->makeCancellableOrder($user);

        $this->mock(StripeRefundService::class, function ($mock) {
            $mock->shouldReceive('refund')
                ->once()
                ->andThrow(new VendorApiException('Stripe refund failed: network error'));
        });

        $response = $this->actingAs($user)->post(route('orders.cancel.destroy', $order));

        $response->assertRedirect(route('order-success', $order));
        $response->assertSessionHas('status', fn ($status) => str_contains($status, 'manually'));

        $this->assertSame('cancelled', $order->refresh()->status);
        Mail::assertNotQueued(OrderCancelledMail::class);
    }
}
