@php
    $vendorLabel = $vendor === 'cj' ? 'CJ Dropshipping' : 'Printful';
    $vendorBadgeStyle = $vendor === 'cj' ? 'background:#DBEAFE;color:#1E40AF' : 'background:#EDE9FE;color:#6D28D9';
    $routeBase = $vendor === 'cj' ? 'admin.products-research-cjd' : 'admin.products-research-printful';
    $otherVendor = $vendor === 'cj' ? 'printful' : 'cj';
    $otherRouteBase = $vendor === 'cj' ? 'admin.products-research-printful' : 'admin.products-research-cjd';
    $otherVendorLabel = $vendor === 'cj' ? 'Printful' : 'CJ Dropshipping';
    $hasFilters = filled($query) || filled($category) || filled($sort) || $trending;
@endphp
<x-admin-layout :title="$vendorLabel.' Research'">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div class="d-flex align-items-center gap-2">
            <span class="badge rounded-pill" style="{{ $vendorBadgeStyle }}">{{ $vendorLabel }}</span>
            <h5 class="mb-0">Catalog Research</h5>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route($otherRouteBase) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left-right"></i> Switch to {{ $otherVendorLabel }}
            </a>
            <a href="{{ route('admin.products-selected') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-bookmark-check"></i> Selected Products
                @if ($selectedCount > 0)
                    <span class="badge bg-danger rounded-pill ms-1">{{ $selectedCount }}</span>
                @endif
            </a>
        </div>
    </div>

    <div class="card-surface p-3 mb-3 d-flex flex-row align-items-start gap-2">
        <i class="bi bi-info-circle text-accent"></i>
        <div class="small text-muted-slate">
            Live search against the <strong>{{ $vendorLabel }} Catalog API</strong>. Results shown are products available for your store — add items you want to sell to your Selected Products list, then customize pricing &amp; listing details before publishing.
        </div>
    </div>

    @if ($error)
        <div class="card-surface p-4 mb-3 text-center">
            <i class="bi bi-exclamation-triangle text-warning fs-3 d-block mb-2"></i>
            <div class="fw-semibold mb-1">Couldn't load the {{ $vendorLabel }} catalog</div>
            <div class="small text-muted-slate mb-3">{{ $error }}</div>
            @role('super-admin')
                <a href="{{ route('admin.settings-gateways') }}" class="btn btn-sm btn-accent">
                    <i class="bi bi-key"></i> Check Gateway Settings
                </a>
            @endrole
        </div>
    @endif

    <form method="GET" class="table-admin p-3 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label small text-muted-slate mb-1" for="research-q">Search</label>
                <input id="research-q" type="search" name="q" value="{{ $query }}" class="form-control form-control-sm" placeholder="Search {{ $vendorLabel }} catalog...">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted-slate mb-1" for="research-category">Category</label>
                <input id="research-category" type="text" name="category" value="{{ $category }}" class="form-control form-control-sm" placeholder="e.g. Electronics">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted-slate mb-1" for="research-sort">Sort by</label>
                <select id="research-sort" name="sort" class="form-select form-select-sm">
                    <option value="" {{ $sort ? '' : 'selected' }}>Relevance</option>
                    <option value="price_asc" {{ $sort === 'price_asc' ? 'selected' : '' }}>Price: Low to High</option>
                    <option value="price_desc" {{ $sort === 'price_desc' ? 'selected' : '' }}>Price: High to Low</option>
                    @if ($vendor === 'cj')
                        <option value="popularity" {{ $sort === 'popularity' ? 'selected' : '' }}>Most Listed (Popularity)</option>
                    @endif
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-accent w-100" type="submit"><i class="bi bi-search"></i> Search</button>
            </div>
        </div>

        @if ($vendor === 'cj')
            <div class="form-check mt-3">
                <input type="checkbox" name="trending" value="1" id="research-trending" class="form-check-input" {{ $trending ? 'checked' : '' }} onchange="this.form.submit()">
                <label class="form-check-label small" for="research-trending">
                    <i class="bi bi-fire text-danger"></i> Trending products only (CJ's own trending classification)
                </label>
            </div>
        @endif

        @if ($hasFilters)
            <div class="d-flex align-items-center flex-wrap gap-2 mt-3">
                <span class="small text-muted-slate">Filters:</span>
                @if ($query)
                    <span class="badge rounded-pill bg-light text-dark border">"{{ $query }}"</span>
                @endif
                @if ($category)
                    <span class="badge rounded-pill bg-light text-dark border">{{ $category }}</span>
                @endif
                @if ($sort === 'popularity')
                    <span class="badge rounded-pill bg-light text-dark border">Most Listed</span>
                @elseif ($sort)
                    <span class="badge rounded-pill bg-light text-dark border">{{ $sort === 'price_asc' ? 'Price ↑' : 'Price ↓' }}</span>
                @endif
                @if ($trending)
                    <span class="badge rounded-pill bg-light text-dark border"><i class="bi bi-fire text-danger"></i> Trending</span>
                @endif
                <a href="{{ route($routeBase) }}" class="small text-decoration-none text-muted-slate"><i class="bi bi-x-circle"></i> Clear all</a>
            </div>
        @endif
    </form>

    @if (! $error)
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="small text-muted-slate">
                @if ($category && ! is_null($totalRecords))
                    {{ count($results) }} {{ Str::plural('match', count($results)) }} on this page for category "{{ $category }}" (out of {{ number_format($totalRecords) }} total {{ $query ? 'results for "'.$query.'"' : 'results' }})
                @elseif (! is_null($totalRecords))
                    {{ number_format($totalRecords) }} {{ Str::plural('result', $totalRecords) }}
                @else
                    {{ count($results) }} {{ Str::plural('result', count($results)) }}
                @endif
                @if ($totalPages)
                    · page {{ $page }} of {{ number_format($totalPages) }}
                @elseif ($page > 1)
                    · page {{ $page }}
                @endif
            </div>
        </div>
    @endif

    @if (empty($results) && ! $error)
        <div class="table-admin p-5 text-center text-muted-slate">
            <i class="bi bi-search fs-2 d-block mb-2"></i>
            @if ($hasFilters)
                No products matched your search. Try a different keyword or clear the category filter.
            @else
                Search the {{ $vendorLabel }} catalog above to start sourcing products.
            @endif
        </div>
    @endif

    <div class="row g-3">
        @foreach ($results as $item)
            @php $isSelected = $selectedExternalIds->contains($item['vendor_external_id']); @endphp
            <div class="col-6 col-md-4 col-lg-3">
                <div class="research-card h-100 d-flex flex-column">
                    <a href="{{ route($routeBase.'.show', $item['vendor_external_id']) }}" class="text-decoration-none text-dark d-block">
                        <div class="research-card__image">
                            <img src="{{ $item['preview_image_url'] ?? 'https://placehold.co/300x300/F8FAFC/64748B?text=No+Image' }}" alt="{{ $item['title'] }}" loading="lazy">
                        </div>
                    </a>
                    <div class="research-card__body d-flex flex-column flex-grow-1">
                        <a href="{{ route($routeBase.'.show', $item['vendor_external_id']) }}" class="text-decoration-none text-dark">
                            <div class="research-card__title">{{ $item['title'] }}</div>
                        </a>
                        <div class="d-flex align-items-center flex-wrap gap-1 mb-2">
                            @if ($item['variant_count'] > 0)
                                <span class="badge rounded-pill bg-light text-muted-slate border">{{ $item['variant_count'] }} {{ Str::plural('variant', $item['variant_count']) }}</span>
                            @endif
                            @if (! empty($item['category_hint']))
                                <span class="badge rounded-pill bg-light text-muted-slate border">{{ $item['category_hint'] }}</span>
                            @endif
                        </div>
                        <div class="mt-auto">
                            @if (isset($item['base_cost']))
                                <div class="research-card__cost mb-2">${{ number_format($item['base_cost'], 2) }} <span class="small text-muted-slate fw-normal">base cost</span></div>
                            @endif
                            <form action="{{ route($routeBase.'.select', $item['vendor_external_id']) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-sm w-100 {{ $isSelected ? 'btn-accent' : 'btn-outline-secondary' }}" {{ $isSelected ? 'disabled' : '' }}>
                                    <i class="bi {{ $isSelected ? 'bi-check-lg' : 'bi-plus-lg' }}"></i> {{ $isSelected ? 'Added to Selection' : 'Add to Selection' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if (! empty($results) && ($page > 1 || $hasNextPage))
        <nav class="mt-4" aria-label="Catalog pagination">
            <ul class="pagination justify-content-center">
                <li class="page-item {{ $page <= 1 ? 'disabled' : '' }}">
                    <a class="page-link" href="{{ $page > 1 ? route($routeBase, array_filter(['q' => $query, 'category' => $category, 'sort' => $sort, 'trending' => $trending ? 1 : null, 'page' => $page - 1])) : '#' }}">Previous</a>
                </li>
                <li class="page-item active"><span class="page-link">{{ $totalPages ? $page.' / '.number_format($totalPages) : $page }}</span></li>
                <li class="page-item {{ $hasNextPage ? '' : 'disabled' }}">
                    <a class="page-link" href="{{ $hasNextPage ? route($routeBase, array_filter(['q' => $query, 'category' => $category, 'sort' => $sort, 'trending' => $trending ? 1 : null, 'page' => $page + 1])) : '#' }}">Next</a>
                </li>
            </ul>
        </nav>
    @endif
</x-admin-layout>
