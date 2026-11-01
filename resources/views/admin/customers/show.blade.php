<x-admin-layout :title="$customer->name">
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">{{ $customer->name }}</h4>
            <div class="text-muted-slate small">{{ $customer->email }}</div>
            <div class="text-muted-slate small">Joined {{ $customer->created_at->format('M j, Y') }}</div>
        </div>
        <a href="{{ route('admin.customers') }}" class="small text-accent text-decoration-none"><i class="bi bi-arrow-left"></i> Back to Customers</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="table-admin p-3 text-center">
                <div class="text-muted-slate small mb-1">Orders</div>
                <div class="fs-4 fw-semibold">{{ $customer->orders->count() }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="table-admin p-3 text-center">
                <div class="text-muted-slate small mb-1">Lifetime Spend</div>
                <div class="fs-4 fw-semibold">${{ number_format($lifetimeSpend, 2) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="table-admin p-3 text-center">
                <div class="text-muted-slate small mb-1">Wishlist Items</div>
                <div class="fs-4 fw-semibold">{{ $customer->wishlists->count() }}</div>
            </div>
        </div>
    </div>

    <h6 class="mb-2">Order History</h6>
    <div class="table-admin p-3">
        <table class="table table-admin align-middle mb-0">
            <thead>
                <tr><th>Order Ref</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($customer->orders as $order)
                    @php $display = $order->displayStatus(); @endphp
                    <tr>
                        <td>{{ $order->order_number }}</td>
                        <td>${{ number_format($order->total, 2) }}</td>
                        <td><span class="{{ $display['class'] }}">{{ $display['label'] }}</span></td>
                        <td>{{ $order->created_at->format('M j, Y') }}</td>
                        <td class="text-end"><a href="{{ route('admin.orders.show', $order) }}" class="small text-accent text-decoration-none">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted-slate py-4">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
