@php
    use App\Services\Seo\StructuredData;

    $colors = $product->variants->pluck('color')->filter()->unique()->values();
    $sizes = $product->variants->pluck('size')->filter()->unique()->values();
    $inStock = $product->variants->contains(fn ($v) => $v->stock_status !== 'out_of_stock');
    $defaultVariant = $product->variants->first(fn ($v) => $v->stock_status !== 'out_of_stock') ?? $product->variants->first();
    // The default-selected swatches must match $defaultVariant (preferring
    // an in-stock variant), not just "first in the list" — otherwise the
    // page can load with Add to Cart already disabled because the first
    // color/size combo happens to be out of stock while others aren't.
    $defaultColor = $defaultVariant?->color ?? $colors->first();
    $defaultSize = $defaultVariant?->size ?? $sizes->first();
    $variantOptions = $product->variants->map(fn ($v) => [
        'id' => $v->id,
        'color' => $v->color,
        'size' => $v->size,
        'in_stock' => $v->stock_status !== 'out_of_stock',
    ])->values();

    $images = collect($product->images ?? [])->filter()->values();
    $isDiscounted = $product->compare_at_price && $product->compare_at_price > $product->price;
    $percentOff = $isDiscounted ? round((1 - $product->price / $product->compare_at_price) * 100) : null;
    $isWishlisted = auth()->user()?->wishlists()->where('product_id', $product->id)->exists() ?? false;

    $metaDescription = StructuredData::excerpt($product->description, 150)
        ?: $product->title.' — $'.number_format($product->price, 2).' at '.config('store.name').'. Secure Stripe checkout and fast, reliable delivery across the USA.';

    $crumbs = [['name' => 'Home', 'url' => route('home')]];
    if ($product->category) {
        $crumbs[] = ['name' => $product->category->name, 'url' => route('products.index', ['cat' => $product->category->slug])];
    }
    $crumbs[] = ['name' => $product->title, 'url' => route('products.show', $product)];
@endphp
<x-store-layout
    :title="$product->title"
    :description="$metaDescription"
    :canonical="route('products.show', $product)"
    :image="$images->first()"
    og-type="product"
    :schema="[StructuredData::product($product), StructuredData::breadcrumbs($crumbs)]">

<main class="container py-4">

  <nav aria-label="Breadcrumb" class="mb-4">
    <ol class="breadcrumb small mb-0">
      <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-muted-slate text-decoration-none">Home</a></li>
      @if ($product->category)
        <li class="breadcrumb-item">
          <a href="{{ route('products.index', ['cat' => $product->category->slug]) }}" class="text-muted-slate text-decoration-none">{{ $product->category->name }}</a>
        </li>
      @endif
      <li class="breadcrumb-item active" aria-current="page">{{ $product->title }}</li>
    </ol>
  </nav>

  <div class="row g-5">
    <div class="col-lg-6">
      <div class="pdp-gallery-main mb-3">
        @if ($images->isNotEmpty())
          <img src="{{ $images->first() }}" alt="{{ $product->title }}" data-gallery-main
               fetchpriority="high" decoding="async">
        @else
          <div class="ph-icon-wrap" style="width:100%;height:100%" role="img" aria-label="No image available for {{ $product->title }}" data-gallery-main>
            <i class="bi bi-box-seam" aria-hidden="true"></i>
          </div>
        @endif
      </div>

      @if ($images->count() > 1)
        <div class="d-flex gap-2 flex-wrap" role="group" aria-label="Product image thumbnails">
          @foreach ($images as $image)
            <button type="button" class="pdp-gallery-thumb @if ($loop->first) active @endif"
                    data-gallery-thumb data-image="{{ $image }}"
                    aria-label="Show image {{ $loop->iteration }} of {{ $images->count() }}"
                    aria-pressed="{{ $loop->first ? 'true' : 'false' }}">
              <img src="{{ $image }}" alt="" loading="lazy" decoding="async">
            </button>
          @endforeach
        </div>
      @endif

      @if ($product->video_path)
        <video src="{{ asset('storage/'.$product->video_path) }}" controls preload="metadata"
               style="width:100%;border-radius:10px" class="mt-3"
               aria-label="Product video for {{ $product->title }}"></video>
      @endif
    </div>

    <div class="col-lg-6">
      <h1 class="h3 mb-2">{{ $product->title }}</h1>

      @if ($product->category)
        <p class="small text-muted-slate mb-2">
          in <a href="{{ route('products.index', ['cat' => $product->category->slug]) }}" class="text-accent text-decoration-none">{{ $product->category->name }}</a>
        </p>
      @endif

      <div class="d-flex align-items-center gap-2 mb-3" data-stock-status aria-live="polite">
        @if ($inStock)
          <span class="text-success small"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> In Stock</span>
        @else
          <span class="text-danger small"><i class="bi bi-x-circle-fill" aria-hidden="true"></i> Out of Stock</span>
        @endif
      </div>

      <div class="mb-4">
        <span class="product-card__price-current fs-2">
          <span class="visually-hidden">Price:</span>${{ number_format($product->price, 2) }}
        </span>
        @if ($isDiscounted)
          <span class="product-card__price-original fs-5 ms-2">
            <span class="visually-hidden">Regular price:</span>${{ number_format($product->compare_at_price, 2) }}
          </span>
          <span class="badge-sale ms-2">{{ $percentOff }}% OFF</span>
        @endif
      </div>

      <div class="d-flex align-items-start gap-3 flex-wrap">
        <form action="{{ route('cart.store') }}" method="POST" id="addToCartForm" class="flex-grow-1">
          @csrf
          <input type="hidden" name="product_variant_id" id="selectedVariantId" value="{{ $defaultVariant?->id }}">

          @if ($colors->isNotEmpty())
            <fieldset class="mb-4">
              <legend class="small fw-semibold mb-2 pdp-option-legend">Color</legend>
              <div class="d-flex flex-wrap gap-2" data-swatch-group="color">
                @foreach ($colors as $color)
                  @php $cssColor = \App\Services\Html\ColorSwatch::resolve($color); @endphp
                  @if ($cssColor)
                    <button type="button" class="swatch-option swatch-color @if ($color === $defaultColor) active @endif"
                            data-color="{{ $color }}"
                            aria-pressed="{{ $color === $defaultColor ? 'true' : 'false' }}"
                            aria-label="Color: {{ $color }}">
                      <span class="swatch-color-fill" style="background:{{ $cssColor }}" aria-hidden="true"></span>
                    </button>
                  @else
                    {{-- Not a real CSS color (e.g. a finish like "Glossy", or a compound name like "Rose Gold") — a colored dot would render invisible, so show the name as a text pill instead. --}}
                    <button type="button" class="swatch-option swatch-color swatch-color--label @if ($color === $defaultColor) active @endif"
                            data-color="{{ $color }}"
                            aria-pressed="{{ $color === $defaultColor ? 'true' : 'false' }}"
                            aria-label="Color: {{ $color }}">{{ $color }}</button>
                  @endif
                @endforeach
              </div>
            </fieldset>
          @endif

          @if ($sizes->isNotEmpty())
            <div class="mb-4">
              <label for="variationSelect" class="small fw-semibold mb-2 d-block">Variation</label>
              <select id="variationSelect" class="form-select" data-swatch-group="size">
                @foreach ($sizes as $size)
                  <option value="{{ $size }}" @selected($size === $defaultSize)>{{ $size }}</option>
                @endforeach
              </select>
            </div>
          @endif

          <div class="d-flex align-items-center gap-3 mb-2 flex-wrap">
            <div class="qty-stepper" data-qty-wrap>
              <button type="button" data-action="qty-decrease" aria-label="Decrease quantity">&minus;</button>
              <label for="qtyInput" class="visually-hidden">Quantity</label>
              <input type="text" id="qtyInput" value="1" name="quantity" data-qty-input
                     inputmode="numeric" autocomplete="off" pattern="[0-9]*">
              <button type="button" data-action="qty-increase" aria-label="Increase quantity">+</button>
            </div>
            <button type="submit" class="btn btn-accent flex-grow-1" @disabled(! $inStock || ! $defaultVariant)>
              <i class="bi bi-cart-plus me-1" aria-hidden="true"></i> Add to Cart
            </button>
          </div>
          <p class="small text-muted-slate mb-4" data-variant-message role="status"></p>
        </form>

        @auth
          <form action="{{ $isWishlisted ? route('wishlist.destroy', $product) : route('wishlist.store') }}" method="POST">
            @csrf
            @if ($isWishlisted) @method('DELETE') @endif
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            <button type="submit" class="product-card__wishlist position-static" style="width:44px;height:44px"
                    aria-pressed="{{ $isWishlisted ? 'true' : 'false' }}"
                    aria-label="{{ $isWishlisted ? 'Remove from your wishlist' : 'Save to your wishlist' }}">
              <i class="bi {{ $isWishlisted ? 'bi-heart-fill text-danger' : 'bi-heart' }}" aria-hidden="true"></i>
            </button>
          </form>
        @else
          <a href="{{ route('login') }}" class="product-card__wishlist position-static d-inline-flex align-items-center justify-content-center"
             style="width:44px;height:44px" aria-label="Sign in to save this product to your wishlist">
            <i class="bi bi-heart" aria-hidden="true"></i>
          </a>
        @endauth
      </div>

      <div class="card-surface p-3 mb-4">
        <h2 class="visually-hidden">Shipping, payment and cancellation</h2>
        <div class="d-flex align-items-start gap-2 mb-2">
          <i class="bi bi-truck text-accent" aria-hidden="true"></i>
          <div class="small">Fast &amp; reliable delivery across USA — free over ${{ config('store.free_shipping_threshold') }}</div>
        </div>
        <div class="d-flex align-items-start gap-2 mb-2">
          <i class="bi bi-shield-check text-accent" aria-hidden="true"></i>
          <div class="small">Secure checkout via Stripe</div>
        </div>
        <div class="d-flex align-items-start gap-2 mb-2">
          <i class="bi bi-arrow-counterclockwise text-accent" aria-hidden="true"></i>
          <div class="small">Free cancellation within 2 hours of order placement</div>
        </div>
        <div class="d-flex align-items-start gap-2">
          <i class="bi bi-question-circle text-accent" aria-hidden="true"></i>
          <div class="small">
            See our <a href="{{ route('policies.shipping-refund') }}" class="text-accent">shipping &amp; refund policy</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  @if ($product->description)
    <section class="mt-5" aria-labelledby="product-description">
      <h2 id="product-description" class="h5 mb-3">Product Description</h2>
      <div class="vendor-description text-muted-slate">{!! $product->description !!}</div>
    </section>
  @endif

  @if ($related->isNotEmpty())
    <section class="mt-5" aria-labelledby="related-products">
      <h2 id="related-products" class="h4 mb-3">You May Also Like</h2>
      <div class="row g-4">
        @foreach ($related as $relatedProduct)
          <div class="col-6 col-md-3">
            <x-product-card :product="$relatedProduct" />
          </div>
        @endforeach
      </div>
    </section>
  @endif
</main>

<script>
  (function () {
    var variants = @json($variantOptions);
    var hiddenInput = document.getElementById('selectedVariantId');
    var submitBtn = document.querySelector('#addToCartForm button[type="submit"]');
    var message = document.querySelector('[data-variant-message]');
    var sizeSelect = document.querySelector('select[data-swatch-group="size"]');
    if (!hiddenInput) return;

    // Tracked here rather than read back from the DOM, because the .active
    // class is applied by the shared swatch handler in main.js, which may
    // run after this listener for the same click.
    var selectedColor = @json($defaultColor);

    function updateSelectedVariant() {
      var size = sizeSelect ? sizeSelect.value : null;
      var match = variants.find(function (v) {
        return (!selectedColor || v.color === selectedColor) && (!size || v.size === size);
      });

      if (match) {
        hiddenInput.value = match.id;
        if (submitBtn) submitBtn.disabled = !match.in_stock;
        if (message) message.textContent = match.in_stock ? '' : 'This combination is sold out — try another option.';
      } else {
        if (submitBtn) submitBtn.disabled = true;
        if (message) message.textContent = 'That combination is not available.';
      }
    }

    document.querySelectorAll('[data-swatch-group="color"] .swatch-option').forEach(function (el) {
      el.addEventListener('click', function () {
        selectedColor = el.dataset.color;
        updateSelectedVariant();
      });
    });

    if (sizeSelect) sizeSelect.addEventListener('change', updateSelectedVariant);

    updateSelectedVariant();
  })();
</script>

</x-store-layout>
