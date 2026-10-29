@php
    use App\Services\Seo\StructuredData;

    $storeName = config('store.name');
    $freeShippingThreshold = config('store.free_shipping_threshold');

    $metaDescription = 'Shop '.$storeName.' for quality home, electronics and everyday essentials. Secure Stripe checkout, free shipping over $'.$freeShippingThreshold.' and fast, reliable delivery across the USA.';

    $homeSchema = [];
    if ($featuredProducts->isNotEmpty()) {
        $homeSchema[] = StructuredData::productList($featuredProducts, 'Featured products at '.$storeName);
    }
@endphp
<x-store-layout
    :description="$metaDescription"
    :canonical="route('home')"
    :schema="$homeSchema">

@if ($slides->isNotEmpty())
<section class="hero-carousel" aria-label="Featured promotions">
  <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel">
    <div class="carousel-inner">
      @foreach ($slides as $slide)
        <div class="carousel-item @if ($loop->first) active @endif"
             role="group" aria-roledescription="slide"
             aria-label="Slide {{ $loop->iteration }} of {{ $slides->count() }}">
          <div class="carousel-slide-media">
            @if ($slide->media_path)
              @if ($slide->media_type === 'video')
                <video src="{{ asset('storage/'.$slide->media_path) }}" autoplay muted loop playsinline
                       aria-label="{{ $slide->headline }}"></video>
              @else
                <img src="{{ asset('storage/'.$slide->media_path) }}" alt="{{ $slide->headline }}"
                     width="1600" height="533"
                     @if ($loop->first) fetchpriority="high" @else loading="lazy" decoding="async" @endif>
              @endif
            @else
              <div class="ph-icon-wrap ph-icon-hero" role="img" aria-label="{{ $slide->headline }}">
                <i class="bi {{ $slide->media_type === 'video' ? 'bi-play-circle' : 'bi-bag-heart' }}" aria-hidden="true"></i>
                <span>{{ $slide->media_type === 'video' ? 'Video slide' : 'Image slide' }} — 1600&times;533 recommended</span>
              </div>
            @endif
            <div class="carousel-slide-overlay">
              <div class="overlay-content">
                @if ($slide->eyebrow_text)
                  <div class="eyebrow-text">{{ $slide->eyebrow_text }}</div>
                @endif
                {{-- Only the first slide is the page's <h1>; the rest are styled
                     to match so the document keeps a single top-level heading. --}}
                @if ($loop->first)
                  <h1 class="display-5 fw-bold mb-2">{{ $slide->headline }}</h1>
                @else
                  <p class="hero-headline mb-2">{{ $slide->headline }}</p>
                @endif
                @if ($slide->subtext)
                  <p class="mb-4" style="color:#e2e8f0">{{ $slide->subtext }}</p>
                @endif
                @if ($slide->cta_label)
                  <a href="{{ $slide->cta_link ?: route('products.index') }}" class="btn btn-accent btn-lg">
                    {{ $slide->cta_label }} <i class="bi bi-arrow-right" aria-hidden="true"></i>
                  </a>
                @endif
              </div>
            </div>
          </div>
        </div>
      @endforeach
    </div>
    @if ($slides->count() > 1)
      <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous slide</span>
      </button>
      <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next slide</span>
      </button>
      <div class="carousel-indicators">
        @foreach ($slides as $slide)
          <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="{{ $loop->index }}"
                  class="@if ($loop->first) active @endif"
                  aria-label="Go to slide {{ $loop->iteration }}: {{ $slide->headline }}"
                  @if ($loop->first) aria-current="true" @endif></button>
        @endforeach
      </div>
    @endif
  </div>
</section>
@else
  {{-- No carousel slides configured — the page still needs its <h1> and a
       primary call to action. --}}
  <section class="py-5" style="background:var(--color-primary)">
    <div class="container text-center text-white py-4">
      <div class="eyebrow-text" style="color:#93c5fd">Welcome to {{ $storeName }}</div>
      <h1 class="display-5 fw-bold mb-3">{{ config('store.tagline') }}</h1>
      <p class="mb-4" style="color:#cbd5e1">
        Curated home, electronics and everyday essentials — with free shipping over ${{ $freeShippingThreshold }}.
      </p>
      <a href="{{ route('products.index') }}" class="btn btn-accent btn-lg">
        Shop All Products <i class="bi bi-arrow-right" aria-hidden="true"></i>
      </a>
    </div>
  </section>
@endif

<div class="hero-trust-row py-3">
  <div class="container">
    <h2 class="visually-hidden">Why shop with {{ $storeName }}</h2>
    <div class="row g-3">
      <div class="col-6 col-md-3 text-center">
        <i class="bi bi-truck fs-5 text-accent" aria-hidden="true"></i>
        <div class="small text-muted-slate mt-1">Fast &amp; Reliable Shipping</div>
      </div>
      <div class="col-6 col-md-3 text-center">
        <i class="bi bi-shield-check fs-5 text-accent" aria-hidden="true"></i>
        <div class="small text-muted-slate mt-1">Secure Payments</div>
      </div>
      <div class="col-6 col-md-3 text-center">
        <i class="bi bi-star fs-5 text-accent" aria-hidden="true"></i>
        <div class="small text-muted-slate mt-1">Top Rated Brands</div>
      </div>
      <div class="col-6 col-md-3 text-center">
        <i class="bi bi-headset fs-5 text-accent" aria-hidden="true"></i>
        <div class="small text-muted-slate mt-1">Dedicated Support</div>
      </div>
    </div>
  </div>
</div>

<main class="container py-5">

  <section class="mb-5" aria-labelledby="shop-by-category">
    <h2 id="shop-by-category" class="h4 mb-3">Shop by Category</h2>
    <div class="row g-3">
      @foreach ($navCategories as $category)
        <div class="col-4 col-md-3 col-lg-auto flex-lg-fill">
          <a href="{{ route('products.index', ['cat' => $category->slug]) }}" class="text-decoration-none text-dark">
            <div class="card-surface text-center py-4">
              <i class="bi {{ $category->icon }} fs-3 text-accent mb-2" aria-hidden="true"></i>
              <div class="small fw-semibold">{{ $category->name }}</div>
            </div>
          </a>
        </div>
      @endforeach
      <div class="col-4 col-md-3 col-lg-auto flex-lg-fill">
        <a href="{{ route('products.index') }}" class="text-decoration-none text-dark">
          <div class="card-surface text-center py-4">
            <i class="bi bi-grid-3x3-gap fs-3 text-accent mb-2" aria-hidden="true"></i>
            <div class="small fw-semibold">More</div>
          </div>
        </a>
      </div>
    </div>
  </section>

  @if ($featuredProducts->isNotEmpty())
    <section class="mb-5" aria-labelledby="featured-products">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 id="featured-products" class="h4 mb-0">Featured Products</h2>
        <a href="{{ route('products.index') }}" class="text-accent text-decoration-none small fw-semibold">
          View All <i class="bi bi-arrow-right" aria-hidden="true"></i>
          <span class="visually-hidden">featured products</span>
        </a>
      </div>
      <div class="row g-4">
        @foreach ($featuredProducts as $product)
          <div class="col-6 col-md-4 col-lg-3">
            <x-product-card :product="$product" :eager="$loop->index < 4" />
          </div>
        @endforeach
      </div>
    </section>
  @endif

  <section class="mb-5" aria-label="Shop highlights">
    <div class="row g-4">
      <div class="col-md-6">
        <div class="rounded-4 p-5 text-white" style="background:var(--color-primary)">
          <h2 class="h4 fw-bold">Upgrade Your Everyday Essentials</h2>
          <p style="color:#cbd5e1">Modern products for a better you.</p>
          <a href="{{ route('products.index') }}" class="btn btn-outline-accent">
            Shop Now <i class="bi bi-arrow-right" aria-hidden="true"></i>
          </a>
        </div>
      </div>
      <div class="col-md-6">
        <div class="rounded-4 p-5" style="background:#DBEAFE">
          <h2 class="h4 fw-bold" style="color:var(--color-primary)">Save More with Our Deals</h2>
          <p class="text-muted-slate">Great products. Better prices.</p>
          <a href="{{ route('products.index', ['deals' => 1]) }}" class="btn btn-accent">
            Explore Deals <i class="bi bi-arrow-right" aria-hidden="true"></i>
          </a>
        </div>
      </div>
    </div>
  </section>

  <section class="mb-2">
    <h2 class="visually-hidden">Our promise</h2>
    <div class="row g-4 text-center text-md-start align-items-center border-top border-bottom py-4" style="border-color:var(--color-border) !important">
      <div class="col-6 col-md-3">
        <i class="bi bi-leaf text-accent fs-4" aria-hidden="true"></i>
        <div class="fw-semibold">Better for You</div>
        <div class="small text-muted-slate">Quality products you'll love.</div>
      </div>
      <div class="col-6 col-md-3">
        <i class="bi bi-globe-americas text-accent fs-4" aria-hidden="true"></i>
        <div class="fw-semibold">Better for the Planet</div>
        <div class="small text-muted-slate">More sustainable choices.</div>
      </div>
      <div class="col-6 col-md-3">
        <i class="bi bi-heart text-accent fs-4" aria-hidden="true"></i>
        <div class="fw-semibold">Better Together</div>
        <div class="small text-muted-slate">A community that shops smart.</div>
      </div>
      <div class="col-6 col-md-3">
        <img src="{{ asset('images/logo.jpeg') }}" alt="{{ $storeName }}" width="84" height="28"
             style="height:28px;width:auto" loading="lazy" decoding="async">
        <div class="small text-muted-slate mt-1">Your world. Your finds.</div>
      </div>
    </div>
  </section>
</main>

</x-store-layout>
