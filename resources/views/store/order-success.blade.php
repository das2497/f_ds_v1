@php
    $firstName = explode(' ', $order->shipping_name)[0] ?? 'there';
@endphp
<x-checkout-layout title="Order Confirmed" step="confirmation">

<div style="max-width:780px;margin:0 auto">

    @if ($order->status === 'pending_payment')
        <div class="text-center mb-4">
            <div class="mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:72px;height:72px;border-radius:50%;background:#FFFBEB">
                <i class="bi bi-hourglass-split fs-1" style="color:#B45309"></i>
            </div>
            <h1 class="h3 mb-2">Processing your payment…</h1>
            <p class="text-muted-slate">We're waiting for payment confirmation for order <strong>#{{ $order->order_number }}</strong>. This page updates automatically once it's verified.</p>
        </div>
    @elseif ($order->status === 'cancelled')
        <div class="text-center mb-4">
            <div class="mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:72px;height:72px;border-radius:50%;background:#FEF2F2">
                <i class="bi bi-x-lg fs-1" style="color:var(--color-danger)"></i>
            </div>
            <h1 class="h3 mb-2">Order #{{ $order->order_number }} was cancelled.</h1>
            <p class="text-muted-slate">
                @if ($order->refunds->where('status', 'succeeded')->isNotEmpty())
                    Your payment has been refunded in full.
                @else
                    A refund is being processed for this order.
                @endif
            </p>
        </div>
    @else
        <div class="text-center mb-4">
            <div class="mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:72px;height:72px;border-radius:50%;background:#DCFCE7">
                <i class="bi bi-check-lg fs-1" style="color:var(--color-success)"></i>
            </div>
            <h1 class="h3 mb-2">Thank you, {{ $firstName }}! Your order is confirmed.</h1>
            <p class="text-muted-slate">A confirmation email has been sent to <strong>{{ $order->email }}</strong></p>
            <div class="fs-5 fw-bold text-accent">Order #{{ $order->order_number }}</div>
            <div class="small text-muted-slate">Placed on {{ $order->created_at->format('F j, Y \a\t g:i A') }}</div>
        </div>

        @if ($order->canStillBeCancelled())
            <div class="cancellation-notice d-flex align-items-start gap-3 mb-4">
                <i class="bi bi-clock-history fs-4" style="color:#B45309"></i>
                <div class="flex-grow-1">
                    <div class="fw-semibold" style="color:#92400E">You can still cancel this order</div>
                    <div class="small" style="color:#92400E">Orders can be cancelled free of charge within the first 2 hours. After that, your order is sent to fulfillment.</div>
                    <div class="mt-2 d-flex align-items-center gap-3 flex-wrap">
                        <span class="deal-countdown" style="background:rgba(180,83,9,0.15);color:#92400E" data-countdown-until="{{ $order->release_at->toIso8601String() }}">--:--:--</span>
                        <a href="{{ route('orders.cancel.show', $order) }}" class="btn btn-sm btn-outline-secondary">Cancel this Order</a>
                    </div>
                </div>
            </div>
        @endif

        <div class="card-surface p-4 mb-4">
            <h6 class="mb-0">What happens next</h6>
            <div class="order-tracker">
                <div class="order-tracker__step done">
                    <div class="order-tracker__dot"><i class="bi bi-check-lg"></i></div>
                    <div class="order-tracker__label">Order Placed</div>
                </div>
                <div class="order-tracker__step active">
                    <div class="order-tracker__dot"><i class="bi bi-hourglass-split"></i></div>
                    <div class="order-tracker__label">Processing</div>
                </div>
                <div class="order-tracker__step">
                    <div class="order-tracker__dot"><i class="bi bi-box-seam"></i></div>
                    <div class="order-tracker__label">Shipped</div>
                </div>
                <div class="order-tracker__step">
                    <div class="order-tracker__dot"><i class="bi bi-house-check"></i></div>
                    <div class="order-tracker__label">Delivered</div>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card-surface p-4 h-100">
                <h6 class="mb-3">Order Items</h6>
                @foreach ($order->items as $item)
                    <div class="d-flex gap-2 mb-3">
                        <div class="cart-item__image" style="width:56px;height:56px">
                            <div class="ph-icon-wrap" role="img" aria-label="{{ $item->product_title }}"><i class="bi bi-box-seam"></i></div>
                        </div>
                        <div class="flex-grow-1 d-flex justify-content-between align-items-center">
                            <div class="small">{{ $item->product_title }} <span class="text-muted-slate">&times;{{ $item->quantity }}</span></div>
                            <div class="small fw-semibold">${{ number_format($item->line_total, 2) }}</div>
                        </div>
                    </div>
                @endforeach
                <hr>
                <div class="row-line"><span class="text-muted-slate">Subtotal</span><span>${{ number_format($order->subtotal, 2) }}</span></div>
                <div class="row-line"><span class="text-muted-slate">Shipping</span><span>${{ number_format($order->shipping_total, 2) }}</span></div>
                <div class="row-line"><span class="text-muted-slate">Tax</span><span>${{ number_format($order->tax_total, 2) }}</span></div>
                <div class="row-line total"><span>Total Paid</span><span>${{ number_format($order->total, 2) }}</span></div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card-surface p-4 h-100">
                <h6 class="mb-3">Shipping Address</h6>
                <div class="small">
                    {{ $order->shipping_name }}<br>
                    {{ $order->shipping_address_line1 }}<br>
                    @if ($order->shipping_address_line2)
                        {{ $order->shipping_address_line2 }}<br>
                    @endif
                    {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_zip }}<br>
                    United States
                </div>
                <hr>
                <h6 class="mb-2">Shipping Method</h6>
                <div class="small text-muted-slate">{{ $order->shipping_method === 'express' ? 'Express Shipping (2–3 business days)' : 'Standard Shipping (5–7 business days)' }}</div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-center gap-3 mt-5 flex-wrap">
        <a href="{{ route('account.dashboard') }}" class="btn btn-accent btn-pill px-4">View Order Details</a>
        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-pill px-4">Continue Shopping</a>
    </div>
</div>

<script>
    (function () {
        var el = document.querySelector('[data-countdown-until]');
        if (!el) return;
        var target = new Date(el.getAttribute('data-countdown-until')).getTime();

        function tick() {
            var remaining = Math.max(0, Math.floor((target - Date.now()) / 1000));
            var h = String(Math.floor(remaining / 3600)).padStart(2, '0');
            var m = String(Math.floor((remaining % 3600) / 60)).padStart(2, '0');
            var s = String(remaining % 60).padStart(2, '0');
            el.textContent = h + ':' + m + ':' + s;
            if (remaining <= 0) { clearInterval(timer); }
        }

        tick();
        var timer = setInterval(tick, 1000);
    })();

    @if ($order->status === 'pending_payment')
        setTimeout(function () { window.location.reload(); }, 4000);
    @endif

    @if ($order->status !== 'pending_payment' && $order->status !== 'cancelled' && request()->cookie('analytics_consent') === 'granted')
        var purchaseEventId = 'purchase-{{ $order->order_number }}';
        @if (config('services.ga4.measurement_id'))
            if (typeof gtag === 'function') {
                gtag('event', 'purchase', {
                    transaction_id: '{{ $order->order_number }}',
                    currency: '{{ $order->currency }}',
                    value: {{ $order->total }},
                });
            }
        @endif
        @if (config('services.meta.pixel_id'))
            if (typeof fbq === 'function') {
                fbq('track', 'Purchase', {
                    currency: '{{ $order->currency }}',
                    value: {{ $order->total }},
                }, { eventID: purchaseEventId });
            }
        @endif
        @if (config('services.tiktok.pixel_code'))
            if (typeof ttq === 'object') {
                ttq.track('CompletePayment', {
                    currency: '{{ $order->currency }}',
                    value: {{ $order->total }},
                }, { event_id: purchaseEventId });
            }
        @endif
    @endif
</script>

</x-checkout-layout>
