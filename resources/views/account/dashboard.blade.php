<x-store-layout title="My Account" robots="noindex, nofollow">

<main class="container py-4">
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
      <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-muted-slate text-decoration-none">Home</a></li>
      <li class="breadcrumb-item active">My Account</li>
    </ol>
  </nav>

  <div class="row g-4">
    <div class="col-lg-3">
      @include('partials.account-nav')
    </div>

    <div class="col-lg-9">
      <h1 class="h4 mb-1">Welcome back, {{ explode(' ', auth()->user()->name)[0] }}</h1>
      <p class="text-muted-slate mb-4">Here's what's happening with your account.</p>

      <div class="row g-3 mb-4">
        <div class="col-6 col-md-6">
          <div class="stat-card">
            <div class="stat-card__label">Total Orders</div>
            <div class="stat-card__value">{{ $totalOrders }}</div>
          </div>
        </div>
        <div class="col-6 col-md-6">
          <div class="stat-card">
            <div class="stat-card__label">Active Orders</div>
            <div class="stat-card__value">{{ $activeOrders }}</div>
          </div>
        </div>
      </div>

      <div class="card-surface p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="mb-0">Recent Orders</h6>
          <a href="{{ route('account.orders') }}" class="small text-accent text-decoration-none">View All <i class="bi bi-arrow-right"></i></a>
        </div>

        @forelse ($orders as $order)
          <div class="order-row">
            <div>
              <a href="{{ route('account.orders.show', $order) }}" class="text-decoration-none text-dark fw-semibold">Order #{{ $order->order_number }}</a>
              <div class="small text-muted-slate">Placed {{ $order->created_at->format('M j, Y') }} &middot; {{ $order->items_count ?? $order->items()->count() }} items</div>
            </div>
            <span class="{{ $order->displayStatus()['class'] }}">{{ $order->displayStatus()['label'] }}</span>
            <div class="fw-semibold">${{ number_format($order->total, 2) }}</div>
            <a href="{{ route('account.orders.show', $order) }}" class="btn btn-sm btn-outline-secondary">View</a>
          </div>
        @empty
          <p class="text-muted-slate small mb-0">You haven't placed any orders yet.</p>
        @endforelse
      </div>
    </div>
  </div>
</main>

</x-store-layout>
