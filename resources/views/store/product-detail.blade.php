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
        'image' => $v->image,
    ])->values();

    $images = collect($product->images ?? [])->filter()->values();
    $isDiscounted = $product->compare_at_price && $product->compare_at_price > $product->price;
    $percentOff = $isDiscounted ? round((1 - $product->price / $product->compare_at_price) * 100) : null;
    $isWishlisted = auth()->user()?->wishlists()->where('product_id', $product->id)->exists() ?? false;

    $reviews = $product->reviews;
    $reviewsCount = $reviews->count();
    $averageRating = $reviewsCount ? $reviews->avg('rating') : null;
    $canReview = $product->isPurchasedBy(auth()->user());
    $hasReviewed = auth()->check() && $reviews->contains('user_id', auth()->id());

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

  @if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif

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

  <section class="mt-5" aria-labelledby="product-reviews" style="max-width:700px">
    <h2 id="product-reviews" class="h5 mb-3">Customer Reviews</h2>

    <div class="d-flex align-items-center gap-2 mb-4">
      <div class="star-rating-display fs-4" aria-hidden="true">
        @for ($i = 1; $i <= 5; $i++)
          <i class="bi {{ $averageRating && $i <= round($averageRating) ? 'bi-star-fill' : 'bi-star' }}"></i>
        @endfor
      </div>
      @if ($reviewsCount)
        <span class="fw-semibold">{{ number_format($averageRating, 1) }}</span>
        <span class="text-muted-slate small">({{ $reviewsCount }} review{{ $reviewsCount === 1 ? '' : 's' }})</span>
      @else
        <span class="text-muted-slate small">No reviews yet</span>
      @endif
    </div>

    @forelse ($reviews as $review)
      <div class="border-bottom pb-3 mb-3">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
          <div>
            <strong>{{ $review->user->name }}</strong>
            <span class="badge bg-success-subtle text-success ms-2 small">Verified Purchase</span>
          </div>
          <span class="small text-muted-slate">{{ $review->created_at->format('M j, Y') }}</span>
        </div>
        <div class="star-rating-display my-1" aria-label="Rated {{ $review->rating }} out of 5">
          @for ($i = 1; $i <= 5; $i++)
            <i class="bi {{ $i <= $review->rating ? 'bi-star-fill' : 'bi-star' }}" aria-hidden="true"></i>
          @endfor
        </div>
        <p class="mb-0 text-muted-slate">{{ $review->body }}</p>
      </div>
    @empty
      {{-- Nothing to show here; the "No reviews yet" message above already covers it. --}}
    @endforelse

    @auth
      @if ($hasReviewed)
        <p class="small text-muted-slate">You've already reviewed this product.</p>
      @elseif ($canReview)
        <div class="card-surface p-3 mt-4">
          <h3 class="h6 mb-3">Write a Review</h3>
          <form action="{{ route('reviews.store', $product) }}" method="POST">
            @csrf
            <div class="mb-3">
              <label class="small fw-semibold mb-2 d-block">Your Rating</label>
              <div class="star-rating-input" data-star-input>
                <input type="hidden" name="rating" value="{{ old('rating') }}">
                @for ($i = 1; $i <= 5; $i++)
                  <button type="button" class="star-input-btn" data-star="{{ $i }}" aria-label="Rate {{ $i }} star{{ $i > 1 ? 's' : '' }}">
                    <i class="bi {{ (int) old('rating') >= $i ? 'bi-star-fill' : 'bi-star' }}" aria-hidden="true"></i>
                  </button>
                @endfor
              </div>
              @error('rating')
                <div class="text-danger small mt-1">{{ $message }}</div>
              @enderror
            </div>
            <div class="mb-3">
              <label for="reviewBody" class="small fw-semibold mb-2 d-block">Your Review</label>
              <textarea name="body" id="reviewBody" class="form-control @error('body') is-invalid @enderror"
                        rows="3" maxlength="2000">{{ old('body') }}</textarea>
              @error('body')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            <button type="submit" class="btn btn-accent">Submit Review</button>
          </form>
        </div>
      @else
        <p class="small text-muted-slate">Only customers who have purchased this product can write a review.</p>
      @endif
    @else
      <p class="small text-muted-slate">
        <a href="{{ route('login') }}" class="text-accent">Sign in</a> to write a review.
      </p>
    @endauth
  </section>

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
    var mainImage = document.querySelector('[data-gallery-main]');
    if (!hiddenInput) return;

    // Tracked here rather than read back from the DOM, because the .active
    // class is applied by the shared swatch handler in main.js, which may
    // run after this listener for the same click.
    var selectedColor = @json($defaultColor);

    // Picking a variant swaps the main photo to that variant's own chosen
    // image (previously it never changed, so choosing "Black" could still
    // show a white product photo) — the image an admin picked for that
    // variant always wins as the main photo.
    function updateGalleryForVariant(variant) {
      if (variant && variant.image && mainImage && mainImage.tagName === 'IMG') {
        mainImage.src = variant.image;
      }
    }

    function updateSelectedVariant() {
      var size = sizeSelect ? sizeSelect.value : null;
      var match = variants.find(function (v) {
        return (!selectedColor || v.color === selectedColor) && (!size || v.size === size);
      });

      if (match) {
        hiddenInput.value = match.id;
        if (submitBtn) submitBtn.disabled = !match.in_stock;
        if (message) message.textContent = match.in_stock ? '' : 'This combination is sold out — try another option.';
        updateGalleryForVariant(match);
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

  (function () {
    document.querySelectorAll('[data-star-input]').forEach(function (group) {
      var hidden = group.querySelector('input[type="hidden"]');
      var buttons = Array.prototype.slice.call(group.querySelectorAll('.star-input-btn'));

      function render(value) {
        buttons.forEach(function (btn) {
          btn.querySelector('i').className = 'bi ' + (parseInt(btn.dataset.star, 10) <= value ? 'bi-star-fill' : 'bi-star');
        });
      }

      buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          hidden.value = btn.dataset.star;
          render(parseInt(btn.dataset.star, 10));
        });
        btn.addEventListener('mouseenter', function () {
          render(parseInt(btn.dataset.star, 10));
        });
        btn.addEventListener('mouseleave', function () {
          render(parseInt(hidden.value, 10) || 0);
        });
      });
    });
  })();
</script>

</x-store-layout>
