<x-admin-layout title="Products">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <form method="GET" class="d-flex gap-2">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search products..." style="width:220px">
            <select name="origin" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="">All Origins</option>
                <option value="printful" @selected(request('origin') === 'printful')>Printful</option>
                <option value="cj" @selected(request('origin') === 'cj')>CJDropshipping</option>
                <option value="manual" @selected(request('origin') === 'manual')>Manual</option>
            </select>
            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.products.create-manual') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil-square"></i> Add Manually</a>
            <a href="{{ route('admin.products-selected') }}" class="btn btn-accent btn-sm"><i class="bi bi-plus-lg"></i> Source New Product</a>
        </div>
    </div>

    <div class="table-admin p-3">
        <table class="table table-admin align-middle mb-0">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Variants</th>
                    <th>Origin</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    <tr>
                        <td class="d-flex align-items-center gap-2">
                            <div class="cart-item__image" style="width:40px;height:40px">
                                <img src="{{ $product->images[0] ?? 'https://placehold.co/80x80/F8FAFC/64748B?text=No+Image' }}">
                            </div>
                            {{ $product->title }}
                        </td>
                        <td>{{ $product->variants->count() }}</td>
                        <td>
                            @if ($product->source_vendor === 'printful')
                                <span class="badge rounded-pill" style="background:#EDE9FE;color:#6D28D9">Printful</span>
                            @elseif ($product->source_vendor === 'cj')
                                <span class="badge rounded-pill" style="background:#DBEAFE;color:#1E40AF">CJDropshipping</span>
                            @elseif ($product->source_vendor === 'manual')
                                <span class="badge rounded-pill" style="background:#F1F5F9;color:#475569">Manual</span>
                            @else
                                <span class="text-muted-slate small">—</span>
                            @endif
                        </td>
                        <td>${{ number_format($product->price, 2) }}</td>
                        <td>
                            @if ($product->is_active)
                                <span class="badge-status-paid">Active</span>
                            @else
                                <span class="badge-status-hold">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="table-actions justify-content-end">
                                <a href="{{ route('admin.products.edit', $product) }}" class="btn-icon btn-icon-accent" title="Edit"><i class="bi bi-pencil"></i></a>
                                @unless (auth()->user()->hasRole('employee') && ! auth()->user()->hasAnyRole(['admin', 'super-admin']))
                                    <form action="{{ route('admin.products.toggle-status', $product) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        @if ($product->is_active)
                                            <button type="submit" class="btn-icon" title="Deactivate"><i class="bi bi-pause-circle"></i></button>
                                        @else
                                            <button type="submit" class="btn-icon btn-icon-positive" title="Activate"><i class="bi bi-play-circle"></i></button>
                                        @endif
                                    </form>
                                    <form action="{{ route('admin.products.destroy', $product) }}" method="POST" onsubmit="return confirm('Permanently delete &quot;{{ $product->title }}&quot;? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-icon btn-icon-danger" title="Delete"><i class="bi bi-trash3"></i></button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted-slate py-4">No products yet — source one from Printful or CJD, or add one manually.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $products->links() }}</div>
</x-admin-layout>
