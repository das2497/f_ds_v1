@php
    $storeName = config('store.name');
    $supportEmail = config('store.contact.email');
    $freeShippingThreshold = config('store.free_shipping_threshold');
    $returnWindow = config('store.return_window_days');
    $socialProfiles = collect(config('store.social', []))
        ->map(fn (string $url, string $network) => [
            'url' => $url,
            'label' => ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'twitter' => 'X (Twitter)'][$network] ?? ucfirst($network),
            'icon' => ['facebook' => 'bi-facebook', 'instagram' => 'bi-instagram', 'twitter' => 'bi-twitter-x'][$network] ?? 'bi-link-45deg',
        ]);
    $footerCategories = ($navCategories ?? collect())->take(4);
@endphp

@guest
  <div class="footer-newsletter">
    <div class="container">
      <div class="row align-items-center g-3">
        <div class="col-md-6">
          <h2 class="h5 mb-1">Get 10% off your first order</h2>
          <p class="mb-0 small" style="color:#cbd5e1">Create a free account for restocks, new arrivals &amp; exclusive deals.</p>
        </div>
        <div class="col-md-6">
          {{-- Carries the address straight into the signup form rather than
               posting to a list we do not yet have. --}}
          <form class="d-flex" action="{{ route('register') }}" method="GET">
            <label for="newsletterEmail" class="visually-hidden">Email address</label>
            <input type="email" name="email" id="newsletterEmail" class="form-control"
                   placeholder="Enter your email" autocomplete="email" required>
            <button type="submit" class="btn btn-accent px-4">Sign Up</button>
          </form>
        </div>
      </div>
    </div>
  </div>
@endguest

<footer class="site-footer pt-4 pb-4">
  <div class="container">
    <h2 class="visually-hidden">{{ $storeName }} site information</h2>

    <div class="footer-trust-row row g-3">
      <div class="col-6 col-md-3"><div class="trust-item"><i class="bi bi-truck" aria-hidden="true"></i> Free shipping over ${{ $freeShippingThreshold }}</div></div>
      <div class="col-6 col-md-3"><div class="trust-item"><i class="bi bi-shield-check" aria-hidden="true"></i> Secure checkout</div></div>
      <div class="col-6 col-md-3"><div class="trust-item"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> {{ $returnWindow }}-day returns</div></div>
      <div class="col-6 col-md-3"><div class="trust-item"><i class="bi bi-headset" aria-hidden="true"></i> Dedicated support</div></div>
    </div>

    <div class="row g-4 pt-4">
      <nav class="col-6 col-md-3" aria-labelledby="footer-shop-heading">
        <h3 id="footer-shop-heading" class="h6">Shop</h3>
        <a href="{{ route('products.index') }}">All Products</a>
        <a href="{{ route('products.index', ['deals' => 1]) }}">Deals</a>
        @foreach ($footerCategories as $category)
          <a href="{{ route('products.index', ['cat' => $category->slug]) }}">{{ $category->name }}</a>
        @endforeach
      </nav>

      <nav class="col-6 col-md-3" aria-labelledby="footer-account-heading">
        <h3 id="footer-account-heading" class="h6">Account</h3>
        @auth
          <a href="{{ route('account.dashboard') }}">My Account</a>
          <a href="{{ route('account.orders') }}">Order History</a>
          <a href="{{ route('wishlist.index') }}">Wishlist</a>
          <a href="{{ route('profile.edit') }}">Profile Settings</a>
        @else
          <a href="{{ route('login') }}">Sign In</a>
          <a href="{{ route('register') }}">Create Account</a>
          <a href="{{ route('password.request') }}">Forgot Password</a>
        @endauth
      </nav>

      <nav class="col-6 col-md-3" aria-labelledby="footer-help-heading">
        <h3 id="footer-help-heading" class="h6">Help</h3>
        <a href="{{ route('policies.shipping-refund') }}">Shipping &amp; Refunds</a>
        <a href="{{ route('policies.terms') }}">Terms of Service</a>
        <a href="{{ route('policies.privacy') }}">Privacy Policy</a>
        <a href="mailto:{{ $supportEmail }}">Contact Us</a>
      </nav>

      <div class="col-6 col-md-3">
        <h3 class="h6">{{ $storeName }}</h3>
        <p class="text-muted-slate small mb-3">{{ config('store.tagline') }}. Fast &amp; reliable delivery across USA.</p>
        <p class="small mb-3">
          <a href="mailto:{{ $supportEmail }}" class="text-decoration-none">
            <i class="bi bi-envelope me-1" aria-hidden="true"></i>{{ $supportEmail }}
          </a>
        </p>
        @if ($socialProfiles->isNotEmpty())
          <div class="footer-social">
            @foreach ($socialProfiles as $profile)
              <a href="{{ $profile['url'] }}" aria-label="{{ $storeName }} on {{ $profile['label'] }}"
                 target="_blank" rel="noopener noreferrer me">
                <i class="bi {{ $profile['icon'] }}" aria-hidden="true"></i>
              </a>
            @endforeach
          </div>
        @endif
      </div>
    </div>

    <hr class="my-4">

    <div class="row g-3 align-items-center">
      <div class="col-md-6 small text-muted-slate">&copy; {{ date('Y') }} {{ $storeName }}. All rights reserved.</div>
      <div class="col-md-6 text-md-end small text-muted-slate">
        <i class="bi bi-credit-card me-1" aria-hidden="true"></i> Visa &middot; Mastercard &middot; Amex &middot; Apple Pay &nbsp;|&nbsp;
        <i class="bi bi-shield-check me-1" aria-hidden="true"></i>Secure Payments via Stripe
      </div>
    </div>
  </div>
</footer>
