<x-admin-layout title="Selected Products">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="small text-muted-slate">Products added from Printful/CJD research. Customize pricing &amp; listing details, then publish to your live catalog.</div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.products-research-printful') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-plus-lg"></i> Browse Printful</a>
            <a href="{{ route('admin.products-research-cjd') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-plus-lg"></i> Browse CJD</a>
        </div>
    </div>

    <div class="table-admin p-3">
        <table class="table table-admin align-middle mb-0">
            <thead>
                <tr><th>Product</th><th>Source</th><th>Base Cost</th><th>Date Added</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($candidates as $candidate)
                    <tr>
                        <td class="d-flex align-items-center gap-2">
                            <div class="cart-item__image" style="width:44px;height:44px">
                                <img src="{{ $candidate->preview_image_url ?? 'https://placehold.co/100x100/F8FAFC/64748B?text=No+Image' }}">
                            </div>
                            {{ $candidate->title }}
                        </td>
                        <td>
                            @if ($candidate->vendor === 'printful')
                                <span class="badge rounded-pill" style="background:#EDE9FE;color:#6D28D9">Printful</span>
                            @else
                                <span class="badge rounded-pill" style="background:#DBEAFE;color:#1E40AF">CJDropshipping</span>
                            @endif
                        </td>
                        <td>${{ number_format($candidate->base_cost, 2) }}</td>
                        <td class="small text-muted-slate">{{ $candidate->selected_at?->format('M j, Y') }}</td>
                        <td>
                            @if ($candidate->status === 'listed')
                                <span class="badge-status-paid">Published</span>
                            @else
                                <span class="badge-status-hold">Pending Customization</span>
                            @endif
                        </td>
                        <td>
                            @if ($candidate->status === 'listed')
                                <a href="{{ route('admin.products') }}" class="btn btn-sm btn-outline-secondary">View Listing</a>
                            @else
                                <a href="{{ route('admin.products.create', $candidate) }}" class="btn btn-sm btn-accent">Customize &amp; List</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted-slate py-4">No products selected yet — browse Printful or CJD above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
