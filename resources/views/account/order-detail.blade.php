<x-store-layout title="Order #{{ $order->order_number }}" robots="noindex, nofollow">

<main class="container py-4">
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
      <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-muted-slate text-decoration-none">Home</a></li>
      <li class="breadcrumb-item"><a href="{{ route('account.dashboard') }}" class="text-muted-slate text-decoration-none">My Account</a></li>
      <li class="breadcrumb-item"><a href="{{ route('account.orders') }}" class="text-muted-slate text-decoration-none">Order History</a></li>
      <li class="breadcrumb-item active">Order #{{ $order->order_number }}</li>
    </ol>
  </nav>

  <div class="row g-4">
    <div class="col-lg-3">
      @include('partials.account-nav')
    </div>

    <div class="col-lg-9">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div>
          <h1 class="h4 mb-1">Order #{{ $order->order_number }}</h1>
          <div class="small text-muted-slate">Placed on {{ $order->created_at->format('F j, Y \a\t g:i A') }}</div>
        </div>
        <span class="{{ $order->displayStatus()['class'] }} fs-6">{{ $order->displayStatus()['label'] }}</span>
      </div>

      @if ($order->canStillBeCancelled())
        <div class="cancellation-notice d-flex align-items-start gap-3 mb-4">
          <i class="bi bi-clock-history fs-4" style="color:#B45309"></i>
          <div class="flex-grow-1">
            <div class="fw-semibold" style="color:#92400E">You can still cancel this order</div>
            <div class="small" style="color:#92400E">Free cancellation available for the next:</div>
            <div class="mt-2 d-flex align-items-center gap-3 flex-wrap">
              <span class="deal-countdown" style="background:rgba(180,83,9,0.15);color:#92400E" data-countdown-until="{{ $order->release_at->toIso8601String() }}">--:--:--</span>
              <a href="{{ route('orders.cancel.show', $order) }}" class="btn btn-sm btn-outline-secondary">Cancel this Order</a>
            </div>
          </div>
        </div>
      @endif

      <div class="card-surface p-4 mb-4">
        <h6 class="mb-3">Items</h6>
        <table class="table align-middle mb-0">
          <thead>
            <tr class="small text-muted-slate text-uppercase">
              <th>Item</th>
              <th>Qty</th>
              <th class="text-end">Price</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($order->items as $item)
              <tr>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="cart-item__image" style="width:48px;height:48px"><div class="ph-icon-wrap" role="img" aria-label="{{ $item->product_title }}"><i class="bi bi-box-seam"></i></div></div>
                    <div class="small">{{ $item->product_title }}<br><span class="text-muted-slate">{{ $item->variant_option_label }}</span></div>
                  </div>
                </td>
                <td>{{ $item->quantity }}</td>
                <td class="text-end">${{ number_format($item->line_total, 2) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
        <hr>
        <div class="row-line"><span class="text-muted-slate">Subtotal</span><span>${{ number_format($order->subtotal, 2) }}</span></div>
        <div class="row-line"><span class="text-muted-slate">Shipping</span><span>${{ number_format($order->shipping_total, 2) }}</span></div>
        <div class="row-line"><span class="text-muted-slate">Tax</span><span>${{ number_format($order->tax_total, 2) }}</span></div>
        <div class="row-line total"><span>Total Paid</span><span>${{ number_format($order->total, 2) }}</span></div>
      </div>

      <div class="row g-4">
        <div class="col-md-6">
          <div class="card-surface p-4 h-100">
            <h6 class="mb-2">Shipping Address</h6>
            <div class="small text-muted-slate">
              {{ $order->shipping_name }}<br>
              {{ $order->shipping_address_line1 }}<br>
              @if ($order->shipping_address_line2)
                {{ $order->shipping_address_line2 }}<br>
              @endif
              {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_zip }}<br>
              United States
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card-surface p-4 h-100">
            <h6 class="mb-2">Tracking</h6>
            <p class="small text-muted-slate mb-2">Tracking will appear here once your order ships.</p>
            <a href="#" class="btn btn-sm btn-outline-secondary disabled">Track Package</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

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
</script>

</x-store-layout>
