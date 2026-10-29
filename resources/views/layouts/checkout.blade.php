@php
    $steps = [
        'shipping' => '1. Cart',
        'payment' => '2. Shipping & Payment',
        'confirmation' => '3. Confirmation',
    ];

    $stepOrder = array_keys($steps);
    $currentIndex = array_search($step, $stepOrder, true) ?: 0;
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    {{-- Checkout URLs are per-order and private: never index or follow them. --}}
    @include('partials.seo-head', ['robots' => 'noindex, nofollow', 'schema' => []])
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.marketing-pixels')
</head>
<body class="storefront">

<header class="site-header py-3">
  <div class="container d-flex justify-content-between align-items-center">
    <a class="navbar-brand" href="{{ route('home') }}" aria-label="{{ config('store.name') }} — back to home">
      <img src="{{ asset('images/logo.jpeg') }}" alt="{{ config('store.name') }}" width="120" height="40">
    </a>
    <div class="small text-muted-slate"><i class="bi bi-lock-fill text-success me-1" aria-hidden="true"></i> Secure Checkout</div>
  </div>
</header>

<main class="container py-4">

  @if ($heading)
    <h1 class="visually-hidden">{{ $heading }}</h1>
  @endif

  <nav aria-label="Checkout progress" class="mb-4">
    <ol class="checkout-steps list-unstyled d-flex justify-content-center gap-4 mb-0 small">
      @foreach ($steps as $key => $label)
        @php
          $index = array_search($key, $stepOrder, true);
          $isComplete = $index < $currentIndex;
          $isCurrent = $key === $step;
        @endphp
        <li class="@if ($isComplete || $isCurrent) text-accent fw-semibold @else text-muted-slate @endif"
            @if ($isCurrent) aria-current="step" @endif>
          @if ($isComplete || $isCurrent)
            <i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>
          @endif
          {{ $label }}
          @if ($isCurrent)<span class="visually-hidden">(current step)</span>@endif
        </li>
      @endforeach
    </ol>
  </nav>

  {{ $slot }}
</main>

<footer class="site-footer pt-5 pb-4 mt-5">
  <div class="container text-center small text-muted-slate">
    &copy; {{ date('Y') }} {{ config('store.name') }}. All rights reserved. &nbsp;|&nbsp;
    <i class="bi bi-shield-check me-1" aria-hidden="true"></i>Secure Payments via Stripe
  </div>
</footer>

</body>
</html>
