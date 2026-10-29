@props([
    'heading',
    'description' => null,
    'updated' => null,
    'sections' => [],
])

@php
    use App\Services\Seo\StructuredData;

    $pageUrl = url()->current();
    $policySchema = [
        StructuredData::policyPage($heading, $pageUrl),
        StructuredData::breadcrumbs([
            ['name' => 'Home', 'url' => route('home')],
            ['name' => $heading, 'url' => $pageUrl],
        ]),
    ];
@endphp

<x-store-layout
    :title="$heading"
    :description="$description"
    :canonical="$pageUrl"
    :schema="$policySchema">

<main class="container py-4">

  <nav aria-label="Breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
      <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-muted-slate text-decoration-none">Home</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ $heading }}</li>
    </ol>
  </nav>

  <div class="row g-5">
    @if (! empty($sections))
      <aside class="col-lg-3 d-none d-lg-block">
        <nav class="policy-toc" aria-labelledby="policy-toc-heading">
          <h2 id="policy-toc-heading" class="h6 filter-group-heading mb-2">On this page</h2>
          @foreach ($sections as $id => $label)
            <a href="#{{ $id }}">{{ $label }}</a>
          @endforeach
        </nav>
      </aside>
    @endif

    <div class="col-lg-9">
      <h1 class="h3 mb-1">{{ $heading }}</h1>
      @if ($updated)
        <p class="small text-muted-slate mb-4">
          Last updated: <time datetime="{{ \Illuminate\Support\Carbon::parse($updated)->toDateString() }}">{{ \Illuminate\Support\Carbon::parse($updated)->format('F j, Y') }}</time>
        </p>
      @endif

      <div class="policy-content">
        {{ $slot }}
      </div>

      <div class="card-surface p-3 mt-5">
        <p class="small mb-0">
          <i class="bi bi-envelope text-accent me-1" aria-hidden="true"></i>
          Questions about this policy? Email
          <a href="mailto:{{ config('store.contact.email') }}" class="text-accent">{{ config('store.contact.email') }}</a>.
        </p>
      </div>
    </div>
  </div>
</main>

</x-store-layout>
