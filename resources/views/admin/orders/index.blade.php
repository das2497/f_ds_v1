<x-admin-layout title="Order Queue">
    <div class="d-flex gap-2 mb-3 flex-wrap">
        <form method="GET" class="d-flex gap-2">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search order ref, customer..." style="width:240px">
            <select name="status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="pending_payment" @selected(request('status') === 'pending_payment')>Pending Payment</option>
                <option value="paid" @selected(request('status') === 'paid')>Paid</option>
                <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
                <option value="refunded" @selected(request('status') === 'refunded')>Refunded</option>
            </select>
            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        </form>
    </div>

    <div class="table-admin p-3">
        <table class="table table-admin align-middle mb-0">
            <thead>
                <tr><th>Order Ref</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    @php $display = $order->displayStatus(); @endphp
                    <tr>
                        <td>{{ $order->order_number }}</td>
                        <td>{{ $order->shipping_name }}</td>
                        <td>${{ number_format($order->total, 2) }}</td>
                        <td><span class="{{ $display['class'] }}">{{ $display['label'] }}</span></td>
                        <td>{{ $order->created_at->format('M j, Y') }}</td>
                        <td><a href="{{ route('admin.orders.show', $order) }}" class="small text-accent text-decoration-none">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted-slate py-4">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $orders->links() }}</div>
</x-admin-layout>
