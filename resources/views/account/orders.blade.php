<x-store-layout title="Order History" robots="noindex, nofollow">

<main class="container py-4">
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
      <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-muted-slate text-decoration-none">Home</a></li>
      <li class="breadcrumb-item"><a href="{{ route('account.dashboard') }}" class="text-muted-slate text-decoration-none">My Account</a></li>
      <li class="breadcrumb-item active">Order History</li>
    </ol>
  </nav>

  <div class="row g-4">
    <div class="col-lg-3">
      @include('partials.account-nav')
    </div>

    <div class="col-lg-9">
      <h1 class="h4 mb-4">Order History</h1>

      <div class="card-surface p-4">
        @forelse ($orders as $order)
          <div class="order-row">
            <div>
              <a href="{{ route('account.orders.show', $order) }}" class="text-decoration-none text-dark fw-semibold">Order #{{ $order->order_number }}</a>
              <div class="small text-muted-slate">Placed {{ $order->created_at->format('M j, Y') }} &middot; {{ $order->items_count }} items</div>
            </div>
            <span class="{{ $order->displayStatus()['class'] }}">{{ $order->displayStatus()['label'] }}</span>
            <div class="fw-semibold">${{ number_format($order->total, 2) }}</div>
            <a href="{{ route('account.orders.show', $order) }}" class="btn btn-sm btn-outline-secondary">View Details</a>
          </div>
        @empty
          <p class="text-muted-slate small mb-0">You haven't placed any orders yet.</p>
        @endforelse
      </div>

      <div class="mt-4">{{ $orders->links() }}</div>
    </div>
  </div>
</main>

</x-store-layout>
