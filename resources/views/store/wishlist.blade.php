<x-store-layout title="My Wishlist"
    description="Products you have saved for later."
    robots="noindex, nofollow">

<main class="container py-4">

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
      <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-muted-slate text-decoration-none">Home</a></li>
      <li class="breadcrumb-item active">My Wishlist</li>
    </ol>
  </nav>

  @if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif

  <h1 class="h3 mb-4">My Wishlist <span class="text-muted-slate fs-5 fw-normal">({{ $products->count() }} items)</span></h1>

  @if ($products->isEmpty())
    <div class="card-surface p-5 text-center">
      <i class="bi bi-heart fs-1 text-muted-slate mb-2 d-block"></i>
      <p class="text-muted-slate">Your wishlist is empty.</p>
      <a href="{{ route('products.index') }}" class="btn btn-accent">Start Shopping</a>
    </div>
  @else
    <div class="row g-4">
      @foreach ($products as $product)
        <div class="col-6 col-md-4 col-lg-3">
          <x-product-card :product="$product" />
        </div>
      @endforeach
    </div>
  @endif
</main>

</x-store-layout>
