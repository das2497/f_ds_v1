<x-checkout-layout title="Cancel Order" step="confirmation">

<main class="container py-5" style="max-width:640px">
  <nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb small mb-0">
      <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-muted-slate text-decoration-none">Home</a></li>
      <li class="breadcrumb-item"><a href="{{ route('order-success', $order) }}" class="text-muted-slate text-decoration-none">Order #{{ $order->order_number }}</a></li>
      <li class="breadcrumb-item active">Cancel</li>
    </ol>
  </nav>

  <div class="card-surface p-4 p-md-5">
    <div class="text-center mb-4">
      <div class="mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:64px;height:64px;border-radius:50%;background:#FEF2F2">
        <i class="bi bi-exclamation-triangle fs-3" style="color:var(--color-danger)"></i>
      </div>
      <h1 class="h4 mb-1">Cancel Order #{{ $order->order_number }}?</h1>
      <p class="text-muted-slate small">This action cannot be undone. Your payment will be refunded in full.</p>
    </div>

    <div class="cancellation-notice d-flex align-items-center gap-3 mb-4 justify-content-center">
      <i class="bi bi-clock-history fs-4" style="color:#B45309"></i>
      <div>
        <div class="small" style="color:#92400E">Time remaining to cancel free of charge:</div>
        <span class="deal-countdown" style="background:rgba(180,83,9,0.15);color:#92400E" data-countdown-until="{{ $order->release_at->toIso8601String() }}">--:--:--</span>
      </div>
    </div>

    <div class="card-surface p-3 mb-4" style="background:var(--color-bg)">
      <div class="row-line"><span class="text-muted-slate">Order Total</span><span class="fw-semibold">${{ number_format($order->total, 2) }}</span></div>
      <div class="row-line mb-0"><span class="text-muted-slate">Refund Amount</span><span class="fw-semibold" style="color:var(--color-success)">${{ number_format($order->total, 2) }}</span></div>
    </div>

    <form action="{{ route('orders.cancel.destroy', $order) }}" method="POST">
      @csrf
      <div class="mb-4">
        <label class="form-label small">Reason for cancellation <span class="text-muted-slate">(optional)</span></label>
        <select name="reason" class="form-select">
          <option value="Changed my mind" selected>Changed my mind</option>
          <option value="Found a better price elsewhere">Found a better price elsewhere</option>
          <option value="Ordered by mistake">Ordered by mistake</option>
          <option value="Shipping time too long">Shipping time too long</option>
          <option value="Other">Other</option>
        </select>
      </div>

      <div class="d-flex gap-3">
        <a href="{{ route('order-success', $order) }}" class="btn btn-outline-secondary flex-grow-1">Keep Order</a>
        <button type="submit" class="btn btn-danger flex-grow-1">Confirm Cancellation</button>
      </div>
    </form>
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
      if (remaining <= 0) clearInterval(timer);
    }
    tick();
    var timer = setInterval(tick, 1000);
  })();
</script>

</x-checkout-layout>
