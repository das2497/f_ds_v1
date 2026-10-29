@php $idPrefix = $idPrefix ?? ''; @endphp

<nav aria-label="Filter by category">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="h6 filter-group-heading mb-0">Categories</h3>
    <a href="{{ route('products.index') }}" class="small text-accent text-decoration-none">Clear all</a>
  </div>
  @foreach ($allCategories as $cat)
    <a href="{{ route('products.index', array_merge(request()->except(['cat', 'sub', 'page']), ['cat' => $cat->slug])) }}"
       class="d-block small mb-2 text-decoration-none {{ $category?->id === $cat->id ? 'text-accent fw-semibold' : 'text-dark' }}"
       @if ($category?->id === $cat->id && ! $subcategory) aria-current="page" @endif>
      <i class="bi {{ $cat->icon }} me-1" aria-hidden="true"></i> {{ $cat->name }}
    </a>
    @if ($category?->id === $cat->id && $subcategories->isNotEmpty())
      <div class="ms-3 mb-2">
        @foreach ($subcategories as $sub)
          <a href="{{ route('products.index', array_merge(request()->except(['sub', 'page']), ['sub' => $sub->slug])) }}"
             class="d-block small mb-1 text-decoration-none {{ $subcategory?->id === $sub->id ? 'text-accent fw-semibold' : 'text-muted-slate' }}"
             @if ($subcategory?->id === $sub->id) aria-current="page" @endif>
            {{ $sub->name }}
          </a>
        @endforeach
      </div>
    @endif
  @endforeach
</nav>

<hr class="my-3">

<form method="GET" action="{{ route('products.index') }}">
  @if ($category) <input type="hidden" name="cat" value="{{ $category->slug }}"> @endif
  @if ($subcategory) <input type="hidden" name="sub" value="{{ $subcategory->slug }}"> @endif
  @if (request()->filled('q')) <input type="hidden" name="q" value="{{ request('q') }}"> @endif
  @if ($sort !== 'newest') <input type="hidden" name="sort" value="{{ $sort }}"> @endif

  <fieldset class="mb-3">
    <legend class="h6 filter-group-heading mb-2">Price Range</legend>
    <div class="d-flex align-items-center gap-2">
      <label for="{{ $idPrefix }}minPrice" class="visually-hidden">Minimum price in dollars</label>
      <input type="number" name="min_price" id="{{ $idPrefix }}minPrice" value="{{ $minPrice }}"
             class="form-control form-control-sm" placeholder="Min" min="0" step="1" inputmode="numeric">
      <span class="text-muted-slate small" aria-hidden="true">to</span>
      <label for="{{ $idPrefix }}maxPrice" class="visually-hidden">Maximum price in dollars</label>
      <input type="number" name="max_price" id="{{ $idPrefix }}maxPrice" value="{{ $maxPrice }}"
             class="form-control form-control-sm" placeholder="Max" min="0" step="1" inputmode="numeric">
    </div>
  </fieldset>

  <fieldset class="mb-3">
    <legend class="h6 filter-group-heading mb-2">Availability</legend>
    <div class="form-check mb-2">
      <input class="form-check-input" type="checkbox" name="in_stock" value="1" id="{{ $idPrefix }}inStockCheck" {{ $inStockOnly ? 'checked' : '' }}>
      <label class="form-check-label small" for="{{ $idPrefix }}inStockCheck">In Stock Only</label>
    </div>
    <div class="form-check">
      <input class="form-check-input" type="checkbox" name="on_sale" value="1" id="{{ $idPrefix }}onSaleCheck" {{ $onSale ? 'checked' : '' }}>
      <label class="form-check-label small" for="{{ $idPrefix }}onSaleCheck">On Sale</label>
    </div>
  </fieldset>

  <button type="submit" class="btn btn-accent btn-sm w-100">Apply Filters</button>
</form>
