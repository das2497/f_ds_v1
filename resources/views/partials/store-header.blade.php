@php
    $activeCategory = request('cat');
    $freeShippingThreshold = config('store.free_shipping_threshold');
@endphp

<div class="site-topbar py-2">
  <div class="container d-flex justify-content-between align-items-center small">
    <div><i class="bi bi-truck me-1" aria-hidden="true"></i> Free shipping on orders over ${{ $freeShippingThreshold }} &nbsp;|&nbsp; Fast &amp; reliable delivery across USA</div>
    <div class="d-none d-md-block"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i> USA</div>
  </div>
</div>

<header class="site-header py-3">
  <div class="container">
    <div class="row align-items-center g-3">
      <div class="col-6 col-lg-2">
        <a class="navbar-brand" href="{{ route('home') }}" aria-label="{{ config('store.name') }} — back to home">
          <img src="{{ asset('images/logo.jpeg') }}" alt="{{ config('store.name') }}" width="120" height="40">
        </a>
      </div>

      <div class="col-6 col-lg-2 order-lg-3 d-flex justify-content-end align-items-center gap-3">
        @auth
          @if (auth()->user()->hasAnyRole(['admin', 'super-admin']))
            <a href="{{ route('admin.dashboard') }}" class="text-dark fs-5" aria-label="Admin panel">
              <i class="bi bi-speedometer2" aria-hidden="true"></i>
            </a>
          @endif
        @endauth

        <a href="{{ auth()->check() ? route('account.dashboard') : route('login') }}" class="text-dark fs-5"
           aria-label="{{ auth()->check() ? 'My account' : 'Sign in to your account' }}">
          <i class="bi bi-person" aria-hidden="true"></i>
        </a>

        <a href="{{ auth()->check() ? route('wishlist.index') : route('login') }}" class="text-dark fs-5 position-relative"
           aria-label="Wishlist{{ $wishlistItemCount > 0 ? " ({$wishlistItemCount} items)" : ' (empty)' }}">
          <i class="bi bi-heart" aria-hidden="true"></i>
          @if ($wishlistItemCount > 0)
            <span class="cart-badge" aria-hidden="true">{{ $wishlistItemCount }}</span>
          @endif
        </a>

        <a href="{{ route('cart.index') }}" class="text-dark fs-5 position-relative"
           aria-label="Shopping cart ({{ $cartItemCount }} items)">
          <i class="bi bi-cart3" aria-hidden="true"></i>
          <span class="cart-badge" aria-hidden="true">{{ $cartItemCount }}</span>
        </a>
      </div>

      <div class="col-12 col-lg-8 order-lg-2">
        <form class="site-search d-flex" role="search" action="{{ route('products.index') }}" method="GET">
          <label for="siteSearchInput" class="visually-hidden">Search products</label>
          <input type="search" name="q" id="siteSearchInput" class="form-control"
                 value="{{ request('q') }}" placeholder="Search for products, brands and more…"
                 autocomplete="off" enterkeyhint="search">
          <button class="btn" type="submit" aria-label="Search">
            <i class="bi bi-search" aria-hidden="true"></i>
          </button>
        </form>
      </div>
    </div>

    <nav class="category-chip-row mt-3 d-lg-none" aria-label="Product categories">
      <a href="{{ route('products.index') }}"
         class="category-chip @if (request()->routeIs('products.index') && ! $activeCategory) active @endif"
         @if (request()->routeIs('products.index') && ! $activeCategory) aria-current="page" @endif>All Products</a>
      @foreach ($navCategories as $category)
        <a href="{{ route('products.index', ['cat' => $category->slug]) }}"
           class="category-chip @if ($activeCategory === $category->slug) active @endif"
           @if ($activeCategory === $category->slug) aria-current="page" @endif>{{ $category->name }}</a>
      @endforeach
      <a href="{{ route('products.index', ['deals' => 1]) }}"
         class="category-chip @if (request()->boolean('deals')) active @endif">Deals</a>
    </nav>
  </div>
</header>
