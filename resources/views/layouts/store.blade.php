@php
    use App\Services\Seo\StructuredData;

    $pageSchema = array_merge(
        [StructuredData::organization(), StructuredData::website()],
        $schema,
    );
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.seo-head', ['schema' => $pageSchema])
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.marketing-pixels')
</head>
<body class="storefront">

@include('partials.store-header')
@include('partials.store-mega-nav')

{{ $slot }}

@include('partials.store-footer')
@include('partials.consent-banner')

</body>
</html>
