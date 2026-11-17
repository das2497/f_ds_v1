@php
    use App\Services\Seo\StructuredData;

    $colors = $product->variants->pluck('color')->filter()->unique()->values();
    $inStock = $product->variants->contains(fn ($v) => $v->stock_status !== 'out_of_stock');
    $defaultVariant = $product->variants->first(fn ($v) => $v->stock_status !== 'out_of_stock') ?? $product->variants->first();
    // The default-selected swatch must match $defaultVariant (preferring an
    // in-stock variant), not just "first in the list" — otherwise the page
    // can load with Add to Cart already disabled because the first color
    // happens to be out of stock while others aren't.
    $defaultColor = $defaultVariant?->color ?? $colors->first();
    // Shown next to "Color:" when a swatch is selected, built straight from
    // the variant's own color + size — not $option_label, which an admin can
    // leave holding just the color after editing size separately in the
    // product form, going stale.
    $variantLabel = fn ($v) => collect([$v->color, $v->size])->filter()->implode(' / ') ?: ($v->option_label ?: 'Variant');
    $defaultSelectionLabel = $defaultVariant ? $variantLabel($defaultVariant) : $defaultColor;
    // The swatch grid groups every same-color variant together (then by
    // size) instead of showing them in raw database order, which otherwise
    // scatters e.g. "Black", "Grey", "Black" across the grid at random.
    $swatchVariants = $product->variants->filter(fn ($v) => $v->color)->sortBy([['color', 'asc'], ['size', 'asc']])->values();
    $variantOptions = $product->variants->map(fn ($v) => [
        'id' => $v->id,
        'color' => $v->color,
        'label' => $variantLabel($v),
        'in_stock' => $v->stock_status !== 'out_of_stock',
        // Falls back to a same-color sibling's photo when this variant has
        // none of its own, matching the image shown on its swatch below.
        'image' => $v->image ?? $product->variants->first(fn ($sibling) => $sibling->color === $v->color && $sibling->image)?->image,
        'price' => (float) ($v->price ?? $product->price),
    ])->values();
    $defaultPrice = (float) ($defaultVariant?->price ?? $product->price);

    $images = collect($product->images ?? [])->filter()->values();
    $isDiscounted = $product->compare_at_price && $product->compare_at_price > $defaultPrice;
    $percentOff = $isDiscounted ? round((1 - $defaultPrice / $product->compare_at_price) * 100) : null;
    $isWishlisted = auth()->user()?->wishlists()->where('product_id', $product->id)->exists() ?? false;

    $reviews = $product->reviews;
    $reviewsCount = $reviews->count();
    $averageRating = $reviewsCount ? $reviews->avg('rating') : null;
    $ratingBreakdown = collect([5, 4, 3, 2, 1])->map(function ($star) use ($reviews, $reviewsCount) {
        $count = $reviews->where('rating', $star)->count();

        return [
            'star' => $star,
            'count' => $count,
            'percent' => $reviewsCount ? round($count / $reviewsCount * 100) : 0,
        ];
    });
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
      <div class="pdp-gallery">
        <div class="pdp-gallery-thumbs" role="group" aria-label="Product image and video thumbnails">
          @foreach ($images as $image)
            <button type="button" class="pdp-gallery-thumb @if ($loop->first) active @endif"
                    data-gallery-thumb data-type="image" data-image="{{ $image }}"
                    aria-label="Show image {{ $loop->iteration }} of {{ $images->count() }}"
                    aria-pressed="{{ $loop->first ? 'true' : 'false' }}">
              <img src="{{ $image }}" alt="{{ $product->title }} — view {{ $loop->iteration }}" loading="lazy" decoding="async">
            </button>
          @endforeach

          @if ($product->video_path)
            <button type="button" class="pdp-gallery-thumb pdp-gallery-thumb--video"
                    data-gallery-thumb data-type="video" data-video="{{ asset('storage/'.$product->video_path) }}"
                    aria-label="Play product video" aria-pressed="false">
              <img src="{{ $images->first() }}" alt="Product video thumbnail" loading="lazy" decoding="async">
              <span class="pdp-gallery-thumb__play" aria-hidden="true"><i class="bi bi-play-fill"></i></span>
            </button>
          @endif
        </div>

        <div class="pdp-gallery-main">
          @if ($images->isNotEmpty())
            <img src="{{ $images->first() }}" alt="{{ $product->title }}" data-gallery-main
                 fetchpriority="high" decoding="async">
          @else
            <div class="ph-icon-wrap" style="width:100%;height:100%" role="img" aria-label="No image available for {{ $product->title }}" data-gallery-main>
              <i class="bi bi-box-seam" aria-hidden="true"></i>
            </div>
          @endif
        </div>
      </div>
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

      <div class="mb-4" data-price-wrap>
        <span class="product-card__price-current fs-2" data-current-price>
          <span class="visually-hidden">Price:</span>${{ number_format($defaultPrice, 2) }}
        </span>
        <span data-compare-price-wrap style="{{ $isDiscounted ? '' : 'display:none' }}">
          <span class="product-card__price-original fs-5 ms-2">
            <span class="visually-hidden">Regular price:</span>${{ number_format($product->compare_at_price, 2) }}
          </span>
          <span class="badge-sale ms-2" data-percent-off>{{ $percentOff }}% OFF</span>
        </span>
      </div>

      <div class="d-flex align-items-start gap-3 flex-wrap">
        <form action="{{ route('cart.store') }}" method="POST" id="addToCartForm" class="flex-grow-1">
          @csrf
          <input type="hidden" name="product_variant_id" id="selectedVariantId" value="{{ $defaultVariant?->id }}">

          @if ($colors->isNotEmpty())
            <fieldset class="mb-4">
              <legend class="small fw-semibold mb-2 pdp-option-legend">Color: <span data-selected-color-label>{{ $defaultSelectionLabel }}</span></legend>
              <div class="d-flex flex-wrap gap-2" data-swatch-group="color">
                {{-- Every variant gets its own thumbnail (not deduplicated by
                     color) — two variants of the same color but a different
                     size/model are still a different photo, so shoppers pick
                     the exact one they want rather than a shared "Black" tile. --}}
                @foreach ($swatchVariants as $variant)
                  @php
                    // Falls back to another variant's photo of the same
                    // color when this exact variant (e.g. a size with no
                    // photo of its own) wasn't given one — only shows as a
                    // plain text pill when no variant of that color has any
                    // photo at all.
                    $swatchImage = $variant->image
                        ?? $product->variants->first(fn ($v) => $v->color === $variant->color && $v->image)?->image;
                    $swatchLabel = $variantLabel($variant);
                  @endphp
                  @if ($swatchImage)
                    <button type="button" class="swatch-option swatch-color-image @if ($variant->id === $defaultVariant?->id) active @endif"
                            data-variant-id="{{ $variant->id }}"
                            data-color="{{ $variant->color }}"
                            aria-pressed="{{ $variant->id === $defaultVariant?->id ? 'true' : 'false' }}"
                            aria-label="Color: {{ $swatchLabel }}">
                      <img src="{{ $swatchImage }}" alt="{{ $swatchLabel }}" loading="lazy" decoding="async">
                    </button>
                  @else
                    <button type="button" class="swatch-option swatch-color swatch-color--label @if ($variant->id === $defaultVariant?->id) active @endif"
                            data-variant-id="{{ $variant->id }}"
                            data-color="{{ $variant->color }}"
                            aria-pressed="{{ $variant->id === $defaultVariant?->id ? 'true' : 'false' }}"
                            aria-label="Color: {{ $swatchLabel }}">{{ $swatchLabel }}</button>
                  @endif
                @endforeach
              </div>
            </fieldset>
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

    <div class="row g-4 align-items-center mb-4">
      <div class="col-12 col-sm-4">
        <div class="display-5 fw-bold lh-1">{{ $reviewsCount ? number_format($averageRating, 1) : '—' }}</div>
        <div class="star-rating-display fs-5 my-1" aria-hidden="true">
          @for ($i = 1; $i <= 5; $i++)
            <i class="bi {{ $averageRating && $i <= round($averageRating) ? 'bi-star-fill' : 'bi-star' }}"></i>
          @endfor
        </div>
        <div class="fw-semibold">
          {{ $reviewsCount ? number_format($reviewsCount).' Review'.($reviewsCount === 1 ? '' : 's') : 'No reviews yet' }}
        </div>
      </div>
      <div class="col-12 col-sm-8">
        @foreach ($ratingBreakdown as $row)
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="d-inline-flex align-items-center gap-1 small" style="width:2.25rem">
              <i class="bi bi-star-fill star-rating-display" aria-hidden="true"></i>{{ $row['star'] }}
            </span>
            <div class="rating-bar-track flex-grow-1" role="img" aria-label="{{ $row['count'] }} {{ $row['star'] }}-star review{{ $row['count'] === 1 ? '' : 's' }}">
              <div class="rating-bar-fill" style="width: {{ $row['percent'] }}%"></div>
            </div>
            <span class="small text-muted-slate text-end" style="width:2.25rem">{{ number_format($row['count']) }}</span>
          </div>
        @endforeach
      </div>
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
    var compareAtPrice = @json($product->compare_at_price ? (float) $product->compare_at_price : null);
    var hiddenInput = document.getElementById('selectedVariantId');
    var submitBtn = document.querySelector('#addToCartForm button[type="submit"]');
    var message = document.querySelector('[data-variant-message]');
    var selectedColorLabel = document.querySelector('[data-selected-color-label]');
    var mainImage = document.querySelector('[data-gallery-main]');
    var currentPriceEl = document.querySelector('[data-current-price]');
    var comparePriceWrap = document.querySelector('[data-compare-price-wrap]');
    var percentOffEl = document.querySelector('[data-percent-off]');
    if (!hiddenInput) return;

    // Keeps the displayed price in sync with whichever variant is selected,
    // falling back to the product's base price for variants without their
    // own override (see $variantOptions above).
    function updatePriceForVariant(variant) {
      if (!variant || !currentPriceEl) return;
      currentPriceEl.innerHTML = '<span class="visually-hidden">Price:</span>$' + variant.price.toFixed(2);

      var isDiscounted = compareAtPrice !== null && compareAtPrice > variant.price;
      if (comparePriceWrap) comparePriceWrap.style.display = isDiscounted ? '' : 'none';
      if (isDiscounted && percentOffEl) {
        percentOffEl.textContent = Math.round((1 - variant.price / compareAtPrice) * 100) + '% OFF';
      }
    }

    // Picking a variant swaps the main photo to that variant's own chosen
    // image (previously it never changed, so choosing "Black" could still
    // show a white product photo) — the image an admin picked for that
    // variant always wins as the main photo.
    function updateGalleryForVariant(variant) {
      if (variant && variant.image && mainImage && mainImage.tagName === 'IMG') {
        mainImage.src = variant.image;
      }
    }

    // Each swatch is its own exact variant now (not a color shared across
    // several variants), so selecting one is a direct id lookup rather than
    // a color match.
    function selectVariant(variant) {
      if (!variant) return;
      hiddenInput.value = variant.id;
      if (selectedColorLabel) selectedColorLabel.textContent = variant.label || variant.color || '';
      if (submitBtn) submitBtn.disabled = !variant.in_stock;
      if (message) message.textContent = variant.in_stock ? '' : 'This option is sold out — try another.';
      updateGalleryForVariant(variant);
      updatePriceForVariant(variant);
    }

    function variantById(id) {
      return variants.find(function (v) { return v.id === id; });
    }

    document.querySelectorAll('[data-swatch-group="color"] .swatch-option').forEach(function (el) {
      el.addEventListener('click', function () {
        selectVariant(variantById(parseInt(el.dataset.variantId, 10)));
      });
    });

    // Syncs the main photo and price to the default variant on load, since
    // the gallery otherwise starts on the product's first general photo
    // rather than the specific variant that's actually pre-selected.
    selectVariant(variantById(@json($defaultVariant?->id)));
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
