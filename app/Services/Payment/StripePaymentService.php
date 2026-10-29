<?php

namespace App\Services\Payment;

use App\Exceptions\VendorApiException;
use App\Models\AppSetting;
use App\Models\Order;
use Stripe\PaymentIntent;
use Stripe\StripeClient;

class StripePaymentService
{
    public function configured(): bool
    {
        return filled($this->secretKey()) && filled($this->publishableKey());
    }

    public function publishableKey(): ?string
    {
        return AppSetting::get('stripe.key') ?: config('services.stripe.key');
    }

    public function secretKey(): ?string
    {
        return AppSetting::get('stripe.secret') ?: config('services.stripe.secret');
    }

    public function webhookSecret(): ?string
    {
        return AppSetting::get('stripe.webhook_secret') ?: config('services.stripe.webhook_secret');
    }

    public function createPaymentIntent(Order $order): PaymentIntent
    {
        if (! $this->configured()) {
            throw VendorApiException::notConfigured('Stripe');
        }

        return $this->client()->paymentIntents->create(
            [
                'amount' => (int) round((float) $order->total * 100),
                'currency' => strtolower($order->currency),
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ],
            ],
            ['idempotency_key' => 'order-'.$order->id.'-intent']
        );
    }

    private function client(): StripeClient
    {
        return new StripeClient($this->secretKey());
    }
}
