@php
    $chartData = ['funnel' => $funnel, 'topCampaigns' => $topCampaigns];
@endphp
<x-admin-layout title="UTM Attribution">
    <form method="GET" class="d-flex gap-2 mb-3 flex-wrap">
        <select name="range" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="30d" @selected($range === '30d')>Last 30 Days</option>
            <option value="7d" @selected($range === '7d')>Last 7 Days</option>
            <option value="quarter" @selected($range === 'quarter')>This Quarter</option>
        </select>
        <select name="channel" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="all" @selected($channel === 'all')>All Channels</option>
            <option value="meta" @selected($channel === 'meta')>Meta</option>
            <option value="tiktok" @selected($channel === 'tiktok')>TikTok</option>
            <option value="google" @selected($channel === 'google')>Google</option>
        </select>
    </form>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card__label">Sessions</div>
                <div class="stat-card__value">{{ number_format($sessions) }}</div>
                <span class="stat-delta {{ $sessionsDelta >= 0 ? 'up' : 'down' }}"><i class="bi bi-arrow-{{ $sessionsDelta >= 0 ? 'up' : 'down' }}-short"></i>{{ abs($sessionsDelta) }}% vs prior period</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card__label">Add to Carts</div>
                <div class="stat-card__value">{{ number_format($addToCarts) }}</div>
                <span class="stat-delta {{ $addToCartsDelta >= 0 ? 'up' : 'down' }}"><i class="bi bi-arrow-{{ $addToCartsDelta >= 0 ? 'up' : 'down' }}-short"></i>{{ abs($addToCartsDelta) }}% vs prior period</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card__label">Purchases</div>
                <div class="stat-card__value">{{ number_format($purchases) }}</div>
                <span class="stat-delta {{ $purchasesDelta >= 0 ? 'up' : 'down' }}"><i class="bi bi-arrow-{{ $purchasesDelta >= 0 ? 'up' : 'down' }}-short"></i>{{ abs($purchasesDelta) }}% vs prior period</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card__label">Conversion Rate</div>
                <div class="stat-card__value">{{ $conversionRate }}%</div>
                <span class="stat-delta {{ $conversionRateDelta >= 0 ? 'up' : 'down' }}"><i class="bi bi-arrow-{{ $conversionRateDelta >= 0 ? 'up' : 'down' }}-short"></i>{{ abs($conversionRateDelta) }}pt vs prior period</span>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <div class="chart-card h-100">
                <div class="chart-card__title mb-1">Conversion Funnel</div>
                <div class="chart-card__subtitle mb-2">Sessions &rarr; Add to Cart &rarr; Purchase</div>
                <div style="height:200px"><canvas id="chartFunnel"></canvas></div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="chart-card h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="chart-card__title">Top Campaigns by Purchases</div>
                        <div class="chart-card__subtitle">Selected period &middot; top 4 by volume</div>
                    </div>
                </div>
                <div style="height:180px"><canvas id="chartCampaigns"></canvas></div>
            </div>
        </div>
    </div>

    <div class="table-admin p-3">
        <h6 class="mb-3">Campaign Performance <span class="small text-muted-slate fw-normal">(full table)</span></h6>
        <table class="table table-admin align-middle mb-0">
            <thead>
                <tr><th>Source</th><th>Medium</th><th>Campaign</th><th>Sessions</th><th>Add to Cart</th><th>Purchases</th><th>Conv. Rate</th></tr>
            </thead>
            <tbody>
                @forelse ($campaignPerformance as $row)
                    <tr>
                        <td>{{ $row['source'] }}</td>
                        <td>{{ $row['medium'] }}</td>
                        <td>{{ $row['campaign'] }}</td>
                        <td>{{ number_format($row['sessions']) }}</td>
                        <td>{{ number_format($row['add_to_cart']) }}</td>
                        <td>{{ number_format($row['purchases']) }}</td>
                        <td class="fw-semibold" style="color:{{ $row['conv_rate'] >= 3 ? 'var(--color-success)' : 'inherit' }}">{{ $row['conv_rate'] }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted-slate py-4">No tracked activity in this period yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <script>
        window.utmChartData = @json($chartData);
    </script>
    @vite(['resources/js/charts-utm.js'])
</x-admin-layout>
