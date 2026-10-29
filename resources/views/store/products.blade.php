@php
    use App\Services\Seo\StructuredData;

    $storeName = config('store.name');
    $pageTitle = $subcategory?->name ?? $category?->name ?? 'All Products';
    $searchTerm = request('q');

    if (filled($searchTerm)) {
        $pageTitle = 'Search results for "'.$searchTerm.'"';
    }

    $activeFilters = collect();
    if ($category) {
        $activeFilters->push(['label' => $category->name, 'remove' => request()->except(['cat', 'sub', 'page'])]);
    }
    if ($subcategory) {
        $activeFilters->push(['label' => $subcategory->name, 'remove' => request()->except(['sub', 'page'])]);
    }
    if (request()->filled('q')) {
        $activeFilters->push(['label' => '"'.request('q').'"', 'remove' => request()->except(['q', 'page'])]);
    }
    if ($minPrice !== null || $maxPrice !== null) {
        $priceLabel = match (true) {
            $minPrice !== null && $maxPrice !== null => '$'.number_format($minPrice, 0).' – $'.number_format($maxPrice, 0),
            $minPrice !== null => 'Over $'.number_format($minPrice, 0),
            default => 'Under $'.number_format($maxPrice, 0),
        };
        $activeFilters->push(['label' => $priceLabel, 'remove' => request()->except(['min_price', 'max_price', 'page'])]);
    }
    if ($inStockOnly) {
        $activeFilters->push(['label' => 'In Stock', 'remove' => request()->except(['in_stock', 'page'])]);
    }
    if ($onSale) {
        $activeFilters->push(['label' => 'On Sale', 'remove' => request()->except(['on_sale', 'page'])]);
    }

    // Search results and facet combinations are thin, near-duplicate pages.
    // They stay crawlable for link discovery but out of the index; plain
    // category and subcategory listings are the canonical landing pages.
    $isFacetedView = filled($searchTerm)
        || $minPrice !== null
        || $maxPrice !== null
        || $inStockOnly
        || $onSale
        || request()->boolean('deals')
        || request()->filled('sort');

    $canonicalParams = array_filter([
        'cat' => $category?->slug,
        'sub' => $subcategory?->slug,
        'page' => $products->currentPage() > 1 ? $products->currentPage() : null,
    ]);
    $canonicalUrl = route('products.index', $canonicalParams);

    $metaDescription = match (true) {
        filled($searchTerm) => 'Products matching "'.$searchTerm.'" at '.$storeName.'. '.$products->total().' results with secure checkout and fast USA delivery.',
        (bool) $subcategory => 'Shop '.$subcategory->name.' at '.$storeName.'. '.$products->total().' products with secure Stripe checkout and fast, reliable delivery across the USA.',
        (bool) $category => 'Shop '.$category->name.' at '.$storeName.'. Browse '.$products->total().' products with secure Stripe checkout and fast, reliable delivery across the USA.',
        default => 'Browse every product at '.$storeName.' — home, electronics and everyday essentials, with free shipping over $'.config('store.free_shipping_threshold').'.',
    };

    $crumbs = [['name' => 'Home', 'url' => route('home')]];
    if ($category) {
        $crumbs[] = ['name' => $category->name, 'url' => route('products.index', ['cat' => $category->slug])];
    }
    if ($subcategory) {
        $crumbs[] = ['name' => $subcategory->name, 'url' => route('products.index', ['cat' => $category?->slug, 'sub' => $subcategory->slug])];
    }
    if (! $category && ! $subcategory) {
        $crumbs[] = ['name' => $pageTitle, 'url' => $canonicalUrl];
    }

    $listingSchema = [StructuredData::breadcrumbs($crumbs)];
    if ($products->isNotEmpty()) {
        $listingSchema[] = StructuredData::productList(
            $products->getCollection(),
            $pageTitle,
            $products->firstItem() ?? 1,
        );
    }
@endphp
<x-store-layout
    :title="$pageTitle"
    :description="$metaDescription"
    :canonical="$canonicalUrl"
    :robots="$isFacetedView ? 'noindex, follow' : null"
    :schema="$listingSchema">

<main class="container py-4">

  <nav aria-label="Breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
      <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-muted-slate text-decoration-none">Home</a></li>
      @if ($category)
        <li class="breadcrumb-item">
          <a href="{{ route('products.index', ['cat' => $category->slug]) }}" class="text-muted-slate text-decoration-none">{{ $category->name }}</a>
        </li>
      @endif
      <li class="breadcrumb-item active" aria-current="page">{{ $subcategory?->name ?? ($category ? 'All '.$category->name : $pageTitle) }}</li>
    </ol>
  </nav>

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h3 mb-0">{{ $pageTitle }}</h1>
      <p class="small text-muted-slate mb-0">
        @if ($products->total() > 0)
          Showing {{ $products->firstItem() }}–{{ $products->lastItem() }} of {{ $products->total() }}
          {{ Str::plural('product', $products->total()) }}
        @else
          No products match your selection
        @endif
      </p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <form method="GET" action="{{ route('products.index') }}" class="d-flex align-items-center gap-2">
        @foreach (request()->except(['sort', 'page']) as $key => $value)
          <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <label for="sortSelect" class="small text-muted-slate mb-0 d-none d-sm-inline">Sort by</label>
        <select name="sort" id="sortSelect" class="form-select form-select-sm" style="width:auto"
                aria-label="Sort products by" onchange="this.form.submit()">
          <option value="newest" @selected($sort === 'newest')>Newest</option>
          <option value="price_asc" @selected($sort === 'price_asc')>Price: Low to High</option>
          <option value="price_desc" @selected($sort === 'price_desc')>Price: High to Low</option>
          <option value="name_asc" @selected($sort === 'name_asc')>Name: A–Z</option>
        </select>
        <noscript><button type="submit" class="btn btn-outline-secondary btn-sm">Apply</button></noscript>
      </form>
      <button class="btn btn-outline-secondary btn-sm d-lg-none" type="button"
              data-bs-toggle="offcanvas" data-bs-target="#filterOffcanvas" aria-controls="filterOffcanvas">
        <i class="bi bi-funnel" aria-hidden="true"></i> Filters
        @if ($activeFilters->isNotEmpty())
          <span class="badge bg-primary ms-1">{{ $activeFilters->count() }}<span class="visually-hidden"> filters active</span></span>
        @endif
      </button>
    </div>
  </div>

  @if ($activeFilters->isNotEmpty())
    <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
      <span class="small text-muted-slate">Active filters:</span>
      @foreach ($activeFilters as $filter)
        <a href="{{ route('products.index', $filter['remove']) }}" class="filter-chip"
           aria-label="Remove filter: {{ $filter['label'] }}">
          {{ $filter['label'] }} <i class="bi bi-x-lg" aria-hidden="true"></i>
        </a>
      @endforeach
      <a href="{{ route('products.index') }}" class="small text-accent text-decoration-none">Clear all</a>
    </div>
  @endif

  <div class="row g-4">
    <aside class="col-lg-3 d-none d-lg-block" aria-labelledby="desktop-filters-heading">
      <div class="card-surface p-3">
        <h2 id="desktop-filters-heading" class="h6 mb-3">Filter products</h2>
        @include('partials.store-product-filters', ['idPrefix' => 'desktop-'])
      </div>
    </aside>

    <div class="col-lg-9">
      @if ($products->isEmpty())
        <div class="card-surface p-5 text-center">
          <i class="bi bi-search fs-2 mb-3 d-block text-muted-slate" aria-hidden="true"></i>
          <h2 class="h5 mb-2">No products found</h2>
          <p class="text-muted-slate small mb-3">
            @if (filled($searchTerm))
              We couldn't find anything matching "{{ $searchTerm }}". Try a different spelling or a broader term.
            @else
              Try removing a filter or browsing another category.
            @endif
          </p>
          <div class="d-flex justify-content-center gap-2 flex-wrap">
            @if ($activeFilters->isNotEmpty())
              <a href="{{ route('products.index') }}" class="btn btn-accent btn-sm">Clear all filters</a>
            @endif
            <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-sm">Browse all products</a>
          </div>
        </div>
      @else
        <h2 class="visually-hidden">{{ $pageTitle }} results</h2>
        <div class="row g-4">
          @foreach ($products as $product)
            <div class="col-6 col-md-4">
              <x-product-card :product="$product" :eager="$loop->index < 3" />
            </div>
          @endforeach
        </div>

        @if ($products->hasPages())
          <nav class="mt-5" aria-label="Product listing pages">{{ $products->links() }}</nav>
        @endif
      @endif
    </div>
  </div>
</main>

<div class="offcanvas offcanvas-start" tabindex="-1" id="filterOffcanvas" aria-labelledby="filterOffcanvasLabel">
  <div class="offcanvas-header">
    <h2 class="h5 mb-0" id="filterOffcanvasLabel">Filters</h2>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close filters"></button>
  </div>
  <div class="offcanvas-body">
    @include('partials.store-product-filters', ['idPrefix' => 'mobile-'])
  </div>
</div>

</x-store-layout>
