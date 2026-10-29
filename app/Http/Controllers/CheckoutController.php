<?php

namespace App\Http\Controllers;

use App\Mail\OrderConfirmedMail;
use App\Models\Cart;
use App\Models\Order;
use App\Services\Analytics\AttributionService;
use App\Services\Analytics\EventTracker;
use App\Services\Cart\CartResolver;
use App\Services\Checkout\ShippingTaxService;
use App\Services\Payment\StripePaymentService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartResolver $cartResolver,
        private readonly ShippingTaxService $shippingTax,
        private readonly StripePaymentService $stripe,
        private readonly EventTracker $tracker,
        private readonly AttributionService $attribution,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $cart = $this->cartResolver->resolve($request);
        $cart->load('items.variant.product');

        if ($cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $subtotal = round($cart->items->sum(fn ($item) => $item->lineTotal()), 2);

        $this->tracker->track($request, 'begin_checkout', ['value' => $subtotal, 'currency' => 'USD']);

        return view('store.checkout', [
            'cart' => $cart,
            'subtotal' => $subtotal,
            'stripeConfigured' => $this->stripe->configured(),
        ]);
    }

    public function store(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'shipping_name' => ['required', 'string', 'max:255'],
            'shipping_address_line1' => ['required', 'string', 'max:255'],
            'shipping_address_line2' => ['nullable', 'string', 'max:255'],
            'shipping_city' => ['required', 'string', 'max:100'],
            'shipping_state' => ['required', 'string', 'size:2'],
            'shipping_zip' => ['required', 'string', 'max:10'],
            'shipping_method' => ['required', 'in:standard,express'],
        ]);

        $cart = $this->cartResolver->resolve($request);
        $cart->load('items.variant.product');

        if ($cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        foreach ($cart->items as $item) {
            abort_if(
                $item->variant->stock_status === 'out_of_stock' || ! $item->variant->is_active,
                422,
                "\"{$item->variant->product->title}\" is no longer available."
            );
        }

        $totals = $this->shippingTax->calculate($cart, $validated['shipping_state'], $validated['shipping_method']);

        $firstTouch = $this->attribution->firstTouch($request);
        $lastTouch = $this->attribution->lastTouch($request);
        $consented = $request->cookie('analytics_consent') === 'granted';

        $order = $this->createOrderWithRetries(function () use ($validated, $cart, $totals, $request, $firstTouch, $lastTouch, $consented) {
            return DB::transaction(function () use ($validated, $cart, $totals, $request, $firstTouch, $lastTouch, $consented) {
                return $this->buildOrder($validated, $cart, $totals, $request, $firstTouch, $lastTouch, $consented);
            });
        });

        Mail::to($order->email)->queue(new OrderConfirmedMail($order));

        return redirect()->route('checkout.pay', $order);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $totals
     * @param  array<string, mixed>  $firstTouch
     * @param  array<string, mixed>  $lastTouch
     */
    private function buildOrder(array $validated, Cart $cart, array $totals, Request $request, array $firstTouch, array $lastTouch, bool $consented): Order
    {
        $order = Order::create([
            ...$validated,
            'order_number' => $this->generateOrderNumber(),
            'user_id' => $request->user()?->id,
            'guest_token' => $request->user() ? null : $request->cookie('cart_token'),
            'shipping_country' => 'US',
            'subtotal' => $totals['subtotal'],
            'shipping_total' => $totals['shipping_total'],
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
            'currency' => 'USD',
            'status' => 'pending_payment',
            'vendor_split_tax_breakdown' => $totals['breakdown'],
            'session_id' => $this->attribution->sessionId($request),
            'first_touch_source' => $firstTouch['source'],
            'first_touch_medium' => $firstTouch['medium'],
            'first_touch_campaign' => $firstTouch['campaign'],
            'first_touch_ad_id' => $firstTouch['ad_id'],
            'last_touch_source' => $lastTouch['source'],
            'last_touch_medium' => $lastTouch['medium'],
            'last_touch_campaign' => $lastTouch['campaign'],
            'last_touch_ad_id' => $lastTouch['ad_id'],
            'fbp' => $consented ? $request->cookie('_fbp') : null,
            'fbc' => $consented ? $request->cookie('_fbc') : null,
            'ttclid' => $consented ? $this->attribution->tiktokClickId($request) : null,
        ]);

        foreach ($cart->items as $item) {
            $variant = $item->variant;
            $product = $variant->product;

            $order->items()->create([
                'product_variant_id' => $variant->id,
                'product_title' => $product->title,
                'variant_option_label' => $variant->option_label,
                'sku' => $variant->sku,
                'source_vendor' => $product->source_vendor,
                'unit_price' => $item->unitPrice(),
                'unit_cost' => $variant->cost_price,
                'quantity' => $item->quantity,
                'line_total' => $item->lineTotal(),
            ]);
        }

        return $order;
    }

    /**
     * generateOrderNumber()'s uniqueness check is a check-then-act with a
     * gap: two concurrent checkouts can both pass the exists() check for
     * the same random number before either inserts, so the loser's insert
     * hits the unique constraint. Retrying with a freshly generated number
     * (instead of letting that exception reach the customer as a 500) is
     * simpler than serializing the whole numbering scheme for a rare clash.
     */
    private function createOrderWithRetries(callable $callback, int $maxAttempts = 3): Order
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $callback();
            } catch (QueryException $e) {
                if (! $this->isDuplicateOrderNumberViolation($e) || $attempt === $maxAttempts) {
                    throw $e;
                }
            }
        }

        throw new \LogicException('Unreachable.');
    }

    /**
     * The insert's SQL always lists every column being written, including
     * "order_number" — so a bare substring check on the message would also
     * match an unrelated NOT NULL violation on a different column in the
     * same insert. Requiring "unique"/"duplicate" alongside it narrows this
     * to the actual unique-constraint violation, across sqlite/MySQL/
     * Postgres error wording.
     */
    private function isDuplicateOrderNumberViolation(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'order_number')
            && (str_contains($message, 'unique') || str_contains($message, 'duplicate'));
    }

    public function pay(Order $order): View
    {
        abort_unless($order->status === 'pending_payment', 404);

        $clientSecret = null;
        $stripeError = null;

        if ($this->stripe->configured()) {
            try {
                $intent = $this->stripe->createPaymentIntent($order);
                $order->payment()->updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'stripe_payment_intent_id' => $intent->id,
                        'amount' => $order->total,
                        'currency' => $order->currency,
                        'status' => $intent->status,
                    ]
                );
                $clientSecret = $intent->client_secret;
            } catch (\Throwable $e) {
                $stripeError = $e->getMessage();
            }
        }

        return view('store.checkout-pay', [
            'order' => $order,
            'clientSecret' => $clientSecret,
            'stripeConfigured' => $this->stripe->configured(),
            'stripeError' => $stripeError,
            'stripeKey' => $this->stripe->publishableKey(),
        ]);
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'SHB-'.random_int(10000, 99999);
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
