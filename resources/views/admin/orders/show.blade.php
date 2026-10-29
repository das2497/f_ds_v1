@php $display = $order->displayStatus(); @endphp
<x-admin-layout :title="'Order #'.$order->order_number">
    <div class="d-flex align-items-center gap-2 mb-3">
        <a href="{{ route('admin.orders') }}" class="text-dark"><i class="bi bi-arrow-left"></i></a>
        <h5 class="mb-0">Order #{{ $order->order_number }}</h5>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div class="small text-muted-slate">
            Placed {{ $order->created_at->format('M j, Y \a\t g:i A') }}
            @if ($order->release_at)
                &middot; release_at: {{ $order->release_at->format('M j, Y \a\t g:i A') }}
            @endif
        </div>
        <span class="{{ $display['class'] }} fs-6">{{ $display['label'] }}</span>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="table-admin p-4 mb-3">
                <h6 class="mb-3">Items &amp; Vendor Split</h6>
                <table class="table table-admin align-middle mb-3">
                    <thead><tr><th>Item</th><th>Qty</th><th>Unit Price</th><th>Supplier Cost</th><th>Vendor</th></tr></thead>
                    <tbody>
                        @foreach ($order->items as $item)
                            <tr>
                                <td>{{ $item->product_title }}@if ($item->variant_option_label)<br><span class="small text-muted-slate">{{ $item->variant_option_label }}</span>@endif</td>
                                <td>{{ $item->quantity }}</td>
                                <td>${{ number_format($item->unit_price, 2) }}</td>
                                <td>${{ number_format($item->unit_cost, 2) }}</td>
                                <td>
                                    @if ($item->source_vendor === 'printful')
                                        <span class="badge rounded-pill" style="background:#EDE9FE;color:#6D28D9">Printful</span>
                                    @elseif ($item->source_vendor === 'cj')
                                        <span class="badge rounded-pill" style="background:#DBEAFE;color:#1E40AF">CJDropshipping</span>
                                    @else
                                        <span class="text-muted-slate small">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if ($order->vendorOrders->isNotEmpty())
                    <div class="row g-3">
                        @foreach ($order->vendorOrders as $vendorOrder)
                            <div class="col-md-6">
                                <div class="border rounded-3 p-3" style="border-color:var(--color-border)!important">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-semibold small">Vendor Order #{{ $vendorOrder->id }}</span>
                                        @if ($vendorOrder->vendor === 'printful')
                                            <span class="badge rounded-pill" style="background:#EDE9FE;color:#6D28D9">Printful</span>
                                        @else
                                            <span class="badge rounded-pill" style="background:#DBEAFE;color:#1E40AF">CJDropshipping</span>
                                        @endif
                                    </div>
                                    <div class="small text-muted-slate">Internal Ref: {{ $vendorOrder->internal_reference }} &middot; External Ref: {{ $vendorOrder->vendor_external_order_id ?: '—' }}</div>
                                    <div class="mt-1">
                                        @if ($vendorOrder->status === 'submitted')
                                            <span class="badge-status-submitted">Submitted</span>
                                        @elseif ($vendorOrder->status === 'failed')
                                            <span class="badge-status-failed">Failed</span>
                                        @else
                                            <span class="badge-status-hold">Awaiting Hold Expiry</span>
                                        @endif
                                        @if ($vendorOrder->status === 'failed')
                                            <form action="{{ route('admin.vendor-orders.retry', $vendorOrder) }}" method="POST" class="d-inline ms-2">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-accent">Force Retry</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="small text-muted-slate">No vendor orders yet — awaiting 2-hour hold expiry (release_at).</div>
                @endif
            </div>

            <div class="table-admin p-4">
                <h6 class="mb-3">Fulfillment Attempts</h6>
                <table class="table table-admin align-middle mb-0">
                    <thead><tr><th>Timestamp</th><th>Vendor</th><th>Attempt</th><th>Result</th></tr></thead>
                    <tbody>
                        @forelse ($order->vendorOrders->flatMap->attempts->sortByDesc('created_at') as $attempt)
                            <tr>
                                <td>{{ $attempt->created_at->format('M j, Y g:i A') }}</td>
                                <td class="text-capitalize">{{ $attempt->vendorOrder->vendor }}</td>
                                <td>#{{ $attempt->attempt_number }}</td>
                                <td>
                                    @if ($attempt->status === 'success' || $attempt->status === 'recovered')
                                        <span class="badge-status-paid">{{ ucfirst($attempt->status) }}</span>
                                    @elseif ($attempt->status === 'skipped')
                                        <span class="badge-status-hold">Skipped</span>
                                    @else
                                        <span class="badge-status-failed">Failed</span>
                                    @endif
                                    @if ($attempt->error_message)
                                        <div class="small text-muted-slate">{{ $attempt->error_message }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td class="text-muted-slate" colspan="4">No vendor submission attempts yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="table-admin p-4 mb-3">
                <h6 class="mb-3">Customer</h6>
                <div class="small">
                    <div class="fw-semibold">{{ $order->shipping_name }}</div>
                    <div class="text-muted-slate">{{ $order->email }}</div>
                    @if ($order->phone)
                        <div class="text-muted-slate">{{ $order->phone }}</div>
                    @endif
                </div>
            </div>

            <div class="table-admin p-4 mb-3">
                <h6 class="mb-3">Shipping Address</h6>
                <div class="small text-muted-slate">
                    {{ $order->shipping_address_line1 }}<br>
                    @if ($order->shipping_address_line2)
                        {{ $order->shipping_address_line2 }}<br>
                    @endif
                    {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_zip }}<br>
                    United States
                </div>
            </div>

            <div class="table-admin p-4">
                <h6 class="mb-3">Order Totals</h6>
                <div class="row-line"><span class="text-muted-slate">Subtotal</span><span>${{ number_format($order->subtotal, 2) }}</span></div>
                <div class="row-line"><span class="text-muted-slate">Shipping</span><span>${{ number_format($order->shipping_total, 2) }}</span></div>
                <div class="row-line"><span class="text-muted-slate">Tax</span><span>${{ number_format($order->tax_total, 2) }}</span></div>
                <div class="row-line total"><span>Total</span><span>${{ number_format($order->total, 2) }}</span></div>
                <div class="row-line mb-0 mt-2"><span class="text-muted-slate">Total Supplier Cost</span><span>${{ number_format($order->totalSupplierCost(), 2) }}</span></div>
                @if ($order->totalRefunded() > 0)
                    <div class="row-line mb-0"><span class="text-muted-slate">Refunded</span><span class="text-danger">-${{ number_format($order->totalRefunded(), 2) }}</span></div>
                @endif

                <a href="{{ route('admin.refunds') }}" class="btn btn-outline-secondary w-100 mt-3 btn-sm">Issue Refund</a>
            </div>
        </div>
    </div>
</x-admin-layout>
