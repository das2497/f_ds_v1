@php
    use App\Services\Seo\StructuredData;

    $storeName = config('store.name');
    $pageTitle = $title ?? null;
    $metaTitle = filled($pageTitle)
        ? "{$pageTitle} — {$storeName}"
        : $storeName.' — '.config('store.tagline');

    $metaDescription = StructuredData::excerpt($description ?? null, 160) ?: config('store.description');
    $canonicalUrl = $canonical ?? url()->current();
    $robotsDirective = $robots ?? null;
    $socialImage = $image ?? asset('images/logo.jpeg');
    $socialType = $ogType ?? 'website';

    // Escaping `<` and `>` keeps a product title or description that contains
    // markup from closing the script element early.
    $jsonLdFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG;
@endphp

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>{{ $metaTitle }}</title>
<meta name="description" content="{{ $metaDescription }}">
<link rel="canonical" href="{{ $canonicalUrl }}">
@if ($robotsDirective)
    <meta name="robots" content="{{ $robotsDirective }}">
@endif

<meta name="theme-color" content="#111827">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="apple-touch-icon" href="{{ asset('images/logo.jpeg') }}">

<meta property="og:site_name" content="{{ $storeName }}">
<meta property="og:type" content="{{ $socialType }}">
<meta property="og:title" content="{{ $metaTitle }}">
<meta property="og:description" content="{{ $metaDescription }}">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:image" content="{{ $socialImage }}">
<meta property="og:locale" content="{{ config('store.locale') }}">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $metaTitle }}">
<meta name="twitter:description" content="{{ $metaDescription }}">
<meta name="twitter:image" content="{{ $socialImage }}">
@if ($handle = config('store.twitter_handle'))
    <meta name="twitter:site" content="{{ Str::start($handle, '@') }}">
@endif

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

@foreach (($schema ?? []) as $graph)
    <script type="application/ld+json">{!! json_encode($graph, $jsonLdFlags) !!}</script>
@endforeach
