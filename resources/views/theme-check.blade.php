<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Theme Check</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="p-5">
    <h1 class="heading-display">Felixes Theme Check</h1>
    <p class="text-muted-slate">Bootstrap 5 + brand palette (theme.css) smoke test.</p>

    <div class="d-flex gap-3 flex-wrap mb-4">
        <button class="btn btn-accent">btn-accent</button>
        <button class="btn btn-outline-secondary btn-pill">btn-pill</button>
        <span class="badge-sale">SALE</span>
        <span class="badge-status-paid">paid</span>
        <span class="badge-status-hold">hold</span>
    </div>

    <div class="card-surface p-4" style="max-width:360px;">
        <i class="bi bi-bag-check" style="font-size:2rem;color:var(--color-accent);"></i>
        <p class="mb-0 mt-2">card-surface + bootstrap-icons</p>
    </div>
</body>
</html>
