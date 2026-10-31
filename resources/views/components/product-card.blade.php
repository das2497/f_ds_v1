@props(['product', 'eager' => false])

@php
    $activeVariants = $product->variants->where('is_active', true);
    $firstVariant = $activeVariants->first(fn ($v) => $v->stock_status !== 'out_of_stock') ?? $activeVariants->first();
    $isOutOfStock = $activeVariants->isEmpty() || $activeVariants->every(fn ($v) => $v->stock_status === 'out_of_stock');
    $isLowStock = ! $isOutOfStock && $firstVariant?->stock_status === 'low_stock';
    $optionCount = $activeVariants->count();
    $isWishlisted = ($wishlistedIds ?? collect())->contains($product->id);
    $isDiscounted = $product->compare_at_price && $product->compare_at_price > $product->price;
    $percentOff = $isDiscounted ? round((1 - $product->price / $product->compare_at_price) * 100) : null;
    $isNew = $product->created_at && $product->created_at->greaterThanOrEqualTo(now()->subDays(14));
    // Only variants with their own photo can actually swap the card's main
    // image when previewed, so colors without one are left out rather than
    // shown as a swatch that does nothing when clicked.
    $colorSwatches = $activeVariants->filter(fn ($v) => $v->color && $v->image)->unique('color')->values();
    $secondImage = $product->images[1] ?? null;
@endphp

<div class="product-card{{ $isOutOfStock ? ' product-card--oos' : '' }}">
    <div class="product-card__image-wrap">
        <div class="product-card__badge-stack">
            @if ($isOutOfStock)
                <span class="badge-oos">Sold Out</span>
            @else
                @if ($isNew)
                    <span class="badge-new">New</span>
                @endif
                @if ($isDiscounted)
                    <span class="badge-sale">{{ $percentOff }}% OFF</span>
                @endif
            @endif
        </div>
        @auth
            <form action="{{ $isWishlisted ? route('wishlist.destroy', $product) : route('wishlist.store') }}" method="POST">
                @csrf
                @if ($isWishlisted) @method('DELETE') @endif
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <button type="submit" class="product-card__wishlist"
                        aria-pressed="{{ $isWishlisted ? 'true' : 'false' }}"
                        aria-label="{{ $isWishlisted ? 'Remove' : 'Save' }} {{ $product->title }} {{ $isWishlisted ? 'from' : 'to' }} your wishlist">
                    <i class="bi {{ $isWishlisted ? 'bi-heart-fill text-danger' : 'bi-heart' }}" aria-hidden="true"></i>
                </button>
            </form>
        @else
            <a href="{{ route('login') }}" class="product-card__wishlist"
               aria-label="Sign in to save {{ $product->title }} to your wishlist">
                <i class="bi bi-heart" aria-hidden="true"></i>
            </a>
        @endauth
        <a href="{{ route('products.show', $product) }}" tabindex="-1" aria-hidden="true">
            @if (! empty($product->images[0]))
                <img src="{{ $product->images[0] }}" alt="" class="product-card__img" data-role="primary"
                     @if ($eager) fetchpriority="high" @else loading="lazy" decoding="async" @endif>
                @if ($secondImage)
                    {{-- A second product photo, shown only on hover, gives a
                         quick alternate angle without leaving the grid. --}}
                    <img src="{{ $secondImage }}" alt="" class="product-card__img product-card__img--hover" loading="lazy" decoding="async">
                @endif
            @else
                <div class="ph-icon-wrap"><i class="bi bi-box-seam" aria-hidden="true"></i></div>
            @endif
        </a>
    </div>
    <div class="product-card__body">
        <a href="{{ route('products.show', $product) }}" class="text-decoration-none text-dark">
            <h3 class="product-card__title">{{ $product->title }}</h3>
        </a>
        <div class="mt-1">
            <span class="product-card__price-current">
                <span class="visually-hidden">Price:</span>${{ number_format($product->price, 2) }}
            </span>
            @if ($isDiscounted)
                <span class="product-card__price-original">
                    <span class="visually-hidden">Regular price:</span>${{ number_format($product->compare_at_price, 2) }}
                </span>
            @endif
        </div>
        @if ($colorSwatches->isNotEmpty())
            {{-- A quick color preview — clicking one swaps the photo above to
                 that variant's own image, so customers can browse colors
                 without leaving the grid. It never replaces the full picker
                 on the product page, so nothing here feeds the cart form. --}}
            <div class="product-card__swatches" role="group" aria-label="Preview other colors">
                @foreach ($colorSwatches->take(5) as $variant)
                    @php $cssColor = \App\Services\Html\ColorSwatch::resolve($variant->color); @endphp
                    <button type="button" class="product-card__swatch js-card-swatch"
                            style="{{ $cssColor ? 'background:'.$cssColor : '' }}"
                            data-image="{{ $variant->image }}"
                            aria-pressed="false"
                            aria-label="Preview in {{ $variant->color }}"></button>
                @endforeach
                @if ($colorSwatches->count() > 5)
                    <span class="product-card__swatch-more">+{{ $colorSwatches->count() - 5 }}</span>
                @endif
            </div>
        @endif
        @if ($isLowStock)
            <div class="product-card__stock-hint"><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i> Only a few left</div>
        @elseif ($optionCount > 1)
            <div class="product-card__options-hint">{{ $optionCount }} options available</div>
        @endif
        @if ($firstVariant && ! $isOutOfStock && $optionCount > 1)
            {{-- Several variants exist (color/size…) — adding $firstVariant
                 straight to the cart would silently pick one for the
                 customer, so this sends them to the product page to choose
                 instead of guessing. --}}
            <a href="{{ route('products.show', $product) }}" class="btn btn-accent product-card__cta w-100 mt-2 btn-sm">
                <i class="bi bi-ui-checks" aria-hidden="true"></i> Options
                <span class="visually-hidden">for {{ $product->title }}</span>
            </a>
        @elseif ($firstVariant && ! $isOutOfStock)
            <form action="{{ route('cart.store') }}" method="POST">
                @csrf
                <input type="hidden" name="product_variant_id" value="{{ $firstVariant->id }}">
                <button type="submit" class="btn btn-accent product-card__cta w-100 mt-2 btn-sm">
                    <i class="bi bi-cart-plus" aria-hidden="true"></i> Add to Cart
                    <span class="visually-hidden">— {{ $product->title }}</span>
                </button>
            </form>
        @else
            <button type="button" class="btn btn-accent product-card__cta w-100 mt-2 btn-sm" disabled>
                Out of Stock<span class="visually-hidden"> — {{ $product->title }}</span>
            </button>
        @endif
    </div>
</div>
