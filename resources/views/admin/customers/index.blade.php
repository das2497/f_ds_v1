<x-admin-layout title="Customers">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="d-flex gap-2 mb-3 flex-wrap">
        <form method="GET" class="d-flex gap-2">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search name, email..." style="width:240px">
            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        </form>
    </div>

    <div class="table-admin p-3">
        <table class="table table-admin align-middle mb-0">
            <thead>
                <tr><th>Name</th><th>Email</th><th>Joined</th><th>Orders</th><th>Lifetime Spend</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($customers as $customer)
                    <tr>
                        <td>{{ $customer->name }}</td>
                        <td>{{ $customer->email }}</td>
                        <td>{{ $customer->created_at->format('M j, Y') }}</td>
                        <td>{{ $customer->orders_count }}</td>
                        <td>${{ number_format($customer->lifetime_spend ?? 0, 2) }}</td>
                        <td>
                            @if ($customer->isSuspended())
                                <span class="badge rounded-pill" style="background:#FEE2E2;color:#991B1B">Suspended</span>
                            @else
                                <span class="badge rounded-pill" style="background:#DCFCE7;color:#166534">Active</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="table-actions justify-content-end">
                                <a href="{{ route('admin.customers.show', $customer) }}" class="btn-icon btn-icon-accent" title="View"><i class="bi bi-eye"></i></a>
                                @if ($customer->isSuspended())
                                    <form action="{{ route('admin.customers.reactivate', $customer) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn-icon btn-icon-positive" title="Reactivate"><i class="bi bi-play-circle"></i></button>
                                    </form>
                                @else
                                    <form action="{{ route('admin.customers.suspend', $customer) }}" method="POST" onsubmit="return confirm('Suspend {{ $customer->name }}\'s account? They won\'t be able to sign in.');">
                                        @csrf
                                        <button type="submit" class="btn-icon" title="Suspend"><i class="bi bi-pause-circle"></i></button>
                                    </form>
                                @endif
                                <form action="{{ route('admin.customers.destroy', $customer) }}" method="POST" onsubmit="return confirm('Permanently delete {{ $customer->name }}\'s account? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-icon btn-icon-danger" title="Delete"><i class="bi bi-trash3"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted-slate py-4">No customers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $customers->links() }}</div>
</x-admin-layout>
