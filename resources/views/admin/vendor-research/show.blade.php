@php
    $vendorLabel = $vendor === 'cj' ? 'CJ Dropshipping' : 'Printful';
    $vendorBadgeStyle = $vendor === 'cj' ? 'background:#DBEAFE;color:#1E40AF' : 'background:#EDE9FE;color:#6D28D9';
    $routeBase = $vendor === 'cj' ? 'admin.products-research-cjd' : 'admin.products-research-printful';
    $gallery = $product['image_gallery'] ?? array_filter([$product['preview_image_url'] ?? null]);
@endphp
<x-admin-layout :title="($product['title'] ?? 'Vendor Product').' — '.$vendorLabel.' Research'">
    <div class="d-flex align-items-center gap-2 mb-3">
        <a href="{{ route($routeBase) }}" class="text-dark"><i class="bi bi-arrow-left"></i></a>
        <h5 class="mb-0">Vendor Product Details <span class="badge rounded-pill" style="{{ $vendorBadgeStyle }}">{{ $vendorLabel }}</span></h5>
    </div>

    @if ($error)
        <div class="alert alert-warning d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle mt-1"></i>
            <div>{{ $error }}</div>
        </div>
    @endif

    @if ($product)
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="pdp-gallery-main mb-3">
                    <img src="{{ $gallery[0] ?? 'https://placehold.co/600x600/F8FAFC/64748B?text=No+Image' }}" data-gallery-main>
                </div>
                @if (count($gallery) > 1)
                    <div class="d-flex gap-2 flex-wrap">
                        @foreach ($gallery as $image)
                            <img src="{{ $image }}" class="pdp-gallery-thumb @if ($loop->first) active @endif" data-gallery-thumb>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="col-lg-7">
                <h4 class="mb-1">{{ $product['title'] }}</h4>
                <div class="small text-muted-slate mb-3">
                    {{ $vendorLabel }} Product #{{ $product['vendor_external_id'] }}
                    @if (! empty($product['vendor_sku']))
                        &middot; SKU {{ $product['vendor_sku'] }}
                    @endif
                    &middot; {{ $product['category_hint'] ?? 'Uncategorized' }}
                </div>

                @if ($product['description'])
                    <div class="vendor-description text-muted-slate mb-3">
                        @if ($vendor === 'cj')
                            {!! $product['description'] !!}
                        @else
                            <p>{{ $product['description'] }}</p>
                        @endif
                    </div>
                @endif

                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-3">
                        <div class="stat-card p-2">
                            <div class="stat-card__label" style="font-size:0.7rem">Base Cost</div>
                            <div class="fw-bold">${{ number_format($product['base_cost'], 2) }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card p-2">
                            <div class="stat-card__label" style="font-size:0.7rem">Variants</div>
                            <div class="fw-bold">{{ count($product['variants']) }}</div>
                        </div>
                    </div>
                    @if (! empty($product['suggested_sell_price']))
                        <div class="col-6 col-md-3">
                            <div class="stat-card p-2">
                                <div class="stat-card__label" style="font-size:0.7rem">Suggested Price</div>
                                <div class="fw-bold">${{ $product['suggested_sell_price'] }}</div>
                            </div>
                        </div>
                    @endif
                    @if (! empty($product['product_weight_lb']))
                        <div class="col-6 col-md-3">
                            <div class="stat-card p-2">
                                <div class="stat-card__label" style="font-size:0.7rem">Product Weight</div>
                                <div class="fw-bold">{{ $product['product_weight_lb'] }} lb</div>
                            </div>
                        </div>
                    @endif
                </div>

                @if (! empty($product['supplier_name']) || ! empty($product['material']) || ($product['listed_count'] ?? null) !== null || ($product['free_shipping'] ?? false))
                    <div class="table-admin p-3 mb-3">
                        <div class="row g-2 small">
                            @if (! empty($product['supplier_name']))
                                <div class="col-6"><span class="text-muted-slate">Supplier:</span> {{ $product['supplier_name'] }}</div>
                            @endif
                            @if (! empty($product['material']))
                                <div class="col-6"><span class="text-muted-slate">Material:</span> {{ $product['material'] }}</div>
                            @endif
                            @if (($product['listed_count'] ?? null) !== null)
                                <div class="col-6"><span class="text-muted-slate">Listed elsewhere:</span> {{ number_format($product['listed_count']) }} times</div>
                            @endif
                            @if ($product['free_shipping'] ?? false)
                                <div class="col-6"><span class="badge-status-paid">Free Shipping</span></div>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="d-flex gap-2">
                    <form action="{{ route($routeBase.'.select', $externalId) }}" method="POST" class="flex-grow-1">
                        @csrf
                        <button type="submit" class="btn btn-accent btn-lg w-100" {{ $isSelected ? 'disabled' : '' }}>
                            <i class="bi {{ $isSelected ? 'bi-check-lg' : 'bi-plus-lg' }}"></i> {{ $isSelected ? 'Added to Selection' : 'Add to Selection' }}
                        </button>
                    </form>
                    <button type="button" id="ai-score-btn" class="btn btn-outline-secondary btn-lg" data-score-url="{{ route($routeBase.'.score', $externalId) }}">
                        <i class="bi bi-stars"></i> AI Score
                    </button>
                </div>

                <div id="ai-score-panel" class="table-admin p-3 mt-3 d-none">
                    <div id="ai-score-loading" class="d-flex align-items-center gap-2 text-muted-slate small">
                        <span class="spinner-border spinner-border-sm"></span> Scoring with Ollama Cloud&hellip;
                    </div>
                    <div id="ai-score-error" class="d-none alert alert-warning mb-0 small"></div>
                    <div id="ai-score-result" class="d-none">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div id="ai-score-number" class="fw-bold fs-3"></div>
                            <div id="ai-score-verdict" class="small"></div>
                            <button type="button" id="ai-score-rescore-btn" class="btn btn-link btn-sm p-0 d-none">Cached result &middot; Re-score</button>
                        </div>
                        <div class="small text-muted-slate fw-semibold mb-1">Why</div>
                        <ul id="ai-score-reasons" class="small mb-2"></ul>
                        <div id="ai-score-risks-wrap" class="d-none">
                            <div class="small text-danger fw-semibold mb-1"><i class="bi bi-exclamation-triangle"></i> Risks</div>
                            <ul id="ai-score-risks" class="small text-danger mb-2"></ul>
                        </div>
                        <div id="ai-score-price-wrap" class="d-none small">
                            <span class="text-muted-slate">AI-suggested sell price:</span> <strong id="ai-score-price"></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            const aiScoreBtn = document.getElementById('ai-score-btn');
            const aiRescoreBtn = document.getElementById('ai-score-rescore-btn');

            async function runAiScore(forceRefresh) {
                const panel = document.getElementById('ai-score-panel');
                const loading = document.getElementById('ai-score-loading');
                const errorBox = document.getElementById('ai-score-error');
                const result = document.getElementById('ai-score-result');

                aiScoreBtn.disabled = true;
                aiRescoreBtn.disabled = true;
                panel.classList.remove('d-none');
                loading.classList.remove('d-none');
                errorBox.classList.add('d-none');
                result.classList.add('d-none');

                try {
                    const response = await fetch(aiScoreBtn.dataset.scoreUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ refresh: forceRefresh }),
                    });
                    const data = await response.json();

                    if (! response.ok) {
                        throw new Error(data.error || 'Scoring failed.');
                    }

                    document.getElementById('ai-score-number').textContent = data.score + '/10';
                    document.getElementById('ai-score-verdict').textContent = data.verdict;
                    aiRescoreBtn.classList.toggle('d-none', ! data.cached);

                    const reasonsList = document.getElementById('ai-score-reasons');
                    reasonsList.innerHTML = '';
                    (data.reasons || []).forEach(reason => {
                        const li = document.createElement('li');
                        li.textContent = reason;
                        reasonsList.appendChild(li);
                    });

                    const risksWrap = document.getElementById('ai-score-risks-wrap');
                    const risksList = document.getElementById('ai-score-risks');
                    risksList.innerHTML = '';
                    if ((data.risks || []).length > 0) {
                        data.risks.forEach(risk => {
                            const li = document.createElement('li');
                            li.textContent = risk;
                            risksList.appendChild(li);
                        });
                        risksWrap.classList.remove('d-none');
                    } else {
                        risksWrap.classList.add('d-none');
                    }

                    const priceWrap = document.getElementById('ai-score-price-wrap');
                    if (data.suggested_price) {
                        document.getElementById('ai-score-price').textContent = '$' + Number(data.suggested_price).toFixed(2);
                        priceWrap.classList.remove('d-none');
                    } else {
                        priceWrap.classList.add('d-none');
                    }

                    loading.classList.add('d-none');
                    result.classList.remove('d-none');
                } catch (err) {
                    loading.classList.add('d-none');
                    errorBox.textContent = err.message;
                    errorBox.classList.remove('d-none');
                } finally {
                    aiScoreBtn.disabled = false;
                    aiRescoreBtn.disabled = false;
                }
            }

            aiScoreBtn?.addEventListener('click', () => runAiScore(false));
            aiRescoreBtn?.addEventListener('click', () => runAiScore(true));
        </script>

        @if (! empty($product['specifications']))
            <div class="table-admin p-4 mt-4">
                <h6 class="mb-3">Specifications</h6>
                <table class="table table-admin align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Product Code</th>
                            <th>Specification</th>
                            <th>Dimensions</th>
                            <th>Package Dimensions</th>
                            <th>Weight</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($product['specifications'] as $spec)
                            <tr>
                                <td class="small text-muted-slate">{{ $spec['code'] ?? '—' }}</td>
                                <td>{{ $spec['spec'] ?? '—' }}</td>
                                <td class="small text-muted-slate">{{ $spec['dimensions'] ?? '—' }}</td>
                                <td class="small text-muted-slate">{{ $spec['package_dimensions'] ?? '—' }}</td>
                                <td class="small text-muted-slate">{{ $spec['weight_lb'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="table-admin p-4 mt-4">
            <h6 class="mb-3">All Variants ({{ count($product['variants']) }})</h6>
            <table class="table table-admin align-middle mb-0">
                <thead><tr><th>Image</th><th>Variant</th><th>Vendor SKU</th><th>Dimensions</th><th>Base Cost</th><th>Weight</th><th>Availability</th></tr></thead>
                <tbody>
                    @foreach ($product['variants'] as $variant)
                        <tr>
                            <td>
                                <img src="{{ $variant['image'] ?? $product['preview_image_url'] ?? 'https://placehold.co/60x60/F8FAFC/64748B?text=No+Image' }}" alt="{{ $variant['option_label'] ?: $product['title'] }}" style="width:48px;height:48px;object-fit:cover;border-radius:6px;">
                            </td>
                            <td>{{ $variant['option_label'] ?: '—' }}</td>
                            <td class="small text-muted-slate">{{ $variant['vendor_sku'] }}</td>
                            <td class="small text-muted-slate">{{ $variant['dimensions_in'] ?? '—' }}</td>
                            <td>${{ number_format($variant['cost_price'], 2) }}</td>
                            <td class="small text-muted-slate">{{ $variant['weight_lb'] ? $variant['weight_lb'].' lb' : '—' }}</td>
                            <td>
                                @if ($variant['in_stock'])
                                    <span class="badge-status-paid">{{ ! empty($variant['stock_qty']) ? number_format($variant['stock_qty']).' avail.' : 'In Stock' }}</span>
                                @else
                                    <span class="badge-status-hold">Out of Stock</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-admin-layout>
