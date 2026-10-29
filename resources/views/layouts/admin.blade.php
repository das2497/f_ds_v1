<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? "{$title} — Shobley Admin" : 'Shobley Admin' }}</title>
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('admin-theme');
                var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-body">

@include('partials.admin-sidebar')

<div class="admin-main">
    @include('partials.admin-topbar')

    <div class="admin-content">
        {{ $slot }}
    </div>
</div>

</body>
</html>
