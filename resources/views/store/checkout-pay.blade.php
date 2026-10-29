<x-checkout-layout title="Payment" step="payment" heading="Complete your payment">

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card-surface p-4">
                <h5 class="mb-3"><i class="bi bi-credit-card me-2 text-accent"></i>Payment</h5>
                <div class="small text-muted-slate mb-3"><i class="bi bi-shield-lock me-1"></i>Payments are securely processed by Stripe. Shobley never stores your card details.</div>

                @if (! $stripeConfigured)
                    <div class="alert alert-warning d-flex align-items-start gap-2">
                        <i class="bi bi-exclamation-triangle mt-1"></i>
                        <div>Stripe isn't connected yet — add <code>STRIPE_KEY</code> / <code>STRIPE_SECRET</code> to <code>.env</code> to accept real payments. Order #{{ $order->order_number }} is saved and waiting (status: <strong>{{ $order->status }}</strong>).</div>
                    </div>
                @elseif ($stripeError)
                    <div class="alert alert-danger">Could not start payment: {{ $stripeError }}</div>
                @else
                    <div id="payment-element" class="border rounded-3 p-3 mb-3" style="border-color:var(--color-border)!important"></div>
                    <div id="payment-message" class="small text-danger mb-2" style="display:none"></div>
                @endif

                <button id="submit-payment" class="btn btn-accent w-100 btn-lg mt-3" @disabled(! $stripeConfigured || $stripeError)>
                    <i class="bi bi-lock-fill me-1"></i> Place Order
                </button>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="cart-summary">
                <h5 class="mb-3">Order Summary</h5>

                @foreach ($order->items as $item)
                    <div class="d-flex justify-content-between small mb-2">
                        <span>{{ $item->product_title }} <span class="text-muted-slate">&times;{{ $item->quantity }}</span></span>
                        <span class="fw-semibold">${{ number_format($item->line_total, 2) }}</span>
                    </div>
                @endforeach

                <hr>

                <div class="row-line"><span class="text-muted-slate">Subtotal</span><span>${{ number_format($order->subtotal, 2) }}</span></div>
                <div class="row-line"><span class="text-muted-slate">Shipping</span><span>${{ number_format($order->shipping_total, 2) }}</span></div>
                <div class="row-line"><span class="text-muted-slate">Tax</span><span>${{ number_format($order->tax_total, 2) }}</span></div>
                <div class="row-line total"><span>Total</span><span>${{ number_format($order->total, 2) }}</span></div>
            </div>
        </div>
    </div>

    @if ($stripeConfigured && $clientSecret)
        <script src="https://js.stripe.com/v3/"></script>
        <script>
            (function () {
                var stripe = Stripe(@json($stripeKey));
                var elements = stripe.elements({ clientSecret: @json($clientSecret) });
                var paymentElement = elements.create('payment');
                paymentElement.mount('#payment-element');

                document.getElementById('submit-payment').addEventListener('click', async function () {
                    var btn = this;
                    btn.disabled = true;
                    var messageEl = document.getElementById('payment-message');

                    var result = await stripe.confirmPayment({
                        elements: elements,
                        confirmParams: {
                            return_url: @json(route('order-success', $order)),
                        },
                    });

                    if (result.error) {
                        messageEl.textContent = result.error.message;
                        messageEl.style.display = 'block';
                        btn.disabled = false;
                    }
                });
            })();
        </script>
    @endif

</x-checkout-layout>
