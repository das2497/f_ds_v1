<x-admin-layout title="Refunds">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card"><div class="stat-card__label">Pending Refunds</div><div class="stat-card__value">{{ $stats['pending'] }}</div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card"><div class="stat-card__label">Refunded (30d)</div><div class="stat-card__value">${{ number_format($stats['last_30_days'], 2) }}</div></div>
        </div>
    </div>

    <div class="table-admin p-3">
        <table class="table table-admin align-middle mb-0">
            <thead>
                <tr><th>Order Ref</th><th>Customer</th><th>Amount</th><th>Reason</th><th>Stripe Ref</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($refunds as $refund)
                    <tr>
                        <td>{{ $refund->order->order_number }}</td>
                        <td>{{ $refund->order->shipping_name }}</td>
                        <td>${{ number_format($refund->amount, 2) }}</td>
                        <td class="small text-muted-slate">{{ $refund->reason }}</td>
                        <td class="small text-muted-slate">{{ $refund->stripe_refund_id ?: '—' }}</td>
                        <td>
                            @if ($refund->status === 'succeeded')
                                <span class="badge-status-paid">Completed</span>
                            @elseif ($refund->status === 'failed')
                                <span class="badge-status-failed">Failed</span>
                            @else
                                <span class="badge-status-hold">Pending</span>
                            @endif
                        </td>
                        <td><a href="{{ route('admin.orders.show', $refund->order) }}" class="small text-accent text-decoration-none">View Order</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted-slate py-4">No refunds yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3 mb-3">{{ $refunds->links() }}</div>

    <div class="card-surface p-4 mt-3">
        <h6 class="mb-3">Issue New Refund</h6>
        <form action="{{ route('admin.refunds.store') }}" method="POST">
            @csrf
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small">Order Reference</label>
                    <input type="text" name="order_number" class="form-control" placeholder="SHB-10237" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Refund Type</label>
                    <select name="type" id="refundType" class="form-select">
                        <option value="full">Full Refund</option>
                        <option value="partial">Partial Refund</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Amount</label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="refundAmount" class="form-control" placeholder="0.00" disabled>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-accent w-100">Process</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('refundType').addEventListener('change', function () {
            document.getElementById('refundAmount').disabled = this.value !== 'partial';
        });
    </script>
</x-admin-layout>
