<x-admin-layout title="Customers">
    <div class="d-flex gap-2 mb-3 flex-wrap">
        <form method="GET" class="d-flex gap-2">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search name, email..." style="width:240px">
            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        </form>
    </div>

    <div class="table-admin p-3">
        <table class="table table-admin align-middle mb-0">
            <thead>
                <tr><th>Name</th><th>Email</th><th>Joined</th><th>Orders</th><th>Lifetime Spend</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($customers as $customer)
                    <tr>
                        <td>{{ $customer->name }}</td>
                        <td>{{ $customer->email }}</td>
                        <td>{{ $customer->created_at->format('M j, Y') }}</td>
                        <td>{{ $customer->orders_count }}</td>
                        <td>${{ number_format($customer->lifetime_spend ?? 0, 2) }}</td>
                        <td class="text-end"><a href="{{ route('admin.customers.show', $customer) }}" class="small text-accent text-decoration-none">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted-slate py-4">No customers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $customers->links() }}</div>
</x-admin-layout>
