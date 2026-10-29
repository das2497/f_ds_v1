<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    {{-- Sign-in and password pages add nothing to search results. --}}
    @include('partials.seo-head', ['robots' => 'noindex, follow', 'schema' => []])
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<header class="site-header py-3">
  <div class="container d-flex justify-content-center">
    <a class="navbar-brand" href="{{ route('home') }}" aria-label="{{ config('store.name') }} — back to home">
      <img src="{{ asset('images/logo.jpeg') }}" alt="{{ config('store.name') }}" width="120" height="40">
    </a>
  </div>
</header>

<main class="auth-wrap">
  <div class="auth-card">
    {{ $slot }}
  </div>
</main>

<footer class="site-footer pt-4 pb-4">
  <div class="container text-center small text-muted-slate">
    &copy; {{ date('Y') }} {{ config('store.name') }}. All rights reserved. &nbsp;|&nbsp;
    <a href="{{ route('policies.privacy') }}" class="d-inline">Privacy</a> &middot;
    <a href="{{ route('policies.terms') }}" class="d-inline">Terms</a>
  </div>
</footer>

</body>
</html>
