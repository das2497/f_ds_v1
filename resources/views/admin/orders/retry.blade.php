<x-admin-layout title="Retry Console">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card-surface p-3 mb-3 d-flex flex-row align-items-start gap-2" style="background:#FEF2F2;border-color:#FECACA">
        <i class="bi bi-exclamation-triangle text-danger"></i>
        <div class="small" style="color:#991B1B">These orders had a vendor API failure (timeout or error). Force Retry re-checks the vendor by our internal reference before submitting again, so it never creates a duplicate order.</div>
    </div>

    <div class="table-admin p-3">
        <table class="table table-admin align-middle mb-0">
            <thead>
                <tr><th>Order Ref</th><th>Vendor</th><th>External Ref</th><th>Last Attempt</th><th>Failure Reason</th><th>Retries</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($failed as $vendorOrder)
                    @php $lastAttempt = $vendorOrder->attempts->sortByDesc('created_at')->first(); @endphp
                    <tr>
                        <td><a href="{{ route('admin.orders.show', $vendorOrder->order) }}" class="text-decoration-none">{{ $vendorOrder->order->order_number }}</a></td>
                        <td>
                            @if ($vendorOrder->vendor === 'printful')
                                <span class="badge rounded-pill" style="background:#EDE9FE;color:#6D28D9">Printful</span>
                            @else
                                <span class="badge rounded-pill" style="background:#DBEAFE;color:#1E40AF">CJDropshipping</span>
                            @endif
                        </td>
                        <td class="text-muted-slate">{{ $vendorOrder->vendor_external_order_id ?: '—' }}</td>
                        <td>{{ $lastAttempt?->created_at->format('M j, Y g:i A') }}</td>
                        <td class="small text-danger">{{ $lastAttempt?->error_message }}</td>
                        <td>{{ $vendorOrder->attempts_count }}</td>
                        <td>
                            <form action="{{ route('admin.vendor-orders.retry', $vendorOrder) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-accent">Force Retry</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted-slate py-4">No failed vendor submissions — nothing to retry.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
