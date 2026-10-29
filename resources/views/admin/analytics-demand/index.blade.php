@php
    $chartData = [
        'trending' => $trending->values(),
        'geo' => $demandByState->map(fn ($row) => ['state' => $row->shipping_state, 'orders' => (int) $row->orders])->values(),
    ];
@endphp
<x-admin-layout title="Demand Forecasting">
    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="chart-card h-100">
                <div class="chart-card__title mb-1">Trending Products</div>
                <div class="chart-card__subtitle mb-2">Ranked by week-over-week growth in views + searches</div>
                <div style="height:220px">
                    @if ($trending->isEmpty())
                        <p class="text-muted-slate small text-center mt-4">Not enough data yet.</p>
                    @else
                        <canvas id="chartTrending"></canvas>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="chart-card h-100">
                <div class="chart-card__title mb-1">Demand by State</div>
                <div class="chart-card__subtitle mb-2">Orders placed, last 30 days &middot; top 6 states</div>
                <div style="height:220px">
                    @if ($demandByState->isEmpty())
                        <p class="text-muted-slate small text-center mt-4">Not enough data yet.</p>
                    @else
                        <canvas id="chartGeo"></canvas>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="table-admin p-3 h-100">
                <h6 class="mb-3">Top Searched Keywords</h6>
                <p class="small text-muted-slate">A low "Avg. Results Found" means customers are searching for something we don't carry — worth sourcing.</p>
                <table class="table table-admin align-middle mb-0">
                    <thead><tr><th>Keyword</th><th>Searches</th><th>Avg. Results Found</th><th>Research</th></tr></thead>
                    <tbody>
                        @forelse ($topKeywords as $row)
                            <tr>
                                <td>{{ $row->search_term }}</td>
                                <td>{{ number_format($row->searches) }}</td>
                                <td>{{ round($row->avg_results) }}</td>
                                <td>
                                    <a href="{{ route('admin.products-research-cjd', ['q' => $row->search_term]) }}" class="btn btn-sm btn-outline-secondary" title="Search CJ Dropshipping for &quot;{{ $row->search_term }}&quot;">CJ</a>
                                    <a href="{{ route('admin.products-research-printful', ['q' => $row->search_term]) }}" class="btn btn-sm btn-outline-secondary" title="Search Printful for &quot;{{ $row->search_term }}&quot;">Printful</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted-slate py-4">No searches logged yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="table-admin p-3 h-100">
                <h6 class="mb-3">Out-of-Stock Demand</h6>
                <p class="small text-muted-slate">Products customers are viewing but currently have an out-of-stock variant.</p>
                <table class="table table-admin align-middle mb-0">
                    <thead><tr><th>Product</th><th>Demand Signals</th><th>Find Replacement</th></tr></thead>
                    <tbody>
                        @forelse ($outOfStockDemand as $row)
                            <tr>
                                <td>{{ $row['title'] }}</td>
                                <td>
                                    @if ($row['level'] === 'High')
                                        <span class="badge-sale">High</span>
                                    @elseif ($row['level'] === 'Medium')
                                        <span class="badge-hot">Medium</span>
                                    @else
                                        <span class="badge-status-hold">Low</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.products-research-cjd', ['q' => $row['title']]) }}" class="btn btn-sm btn-outline-secondary" title="Search CJ Dropshipping for a replacement">CJ</a>
                                    <a href="{{ route('admin.products-research-printful', ['q' => $row['title']]) }}" class="btn btn-sm btn-outline-secondary" title="Search Printful for a replacement">Printful</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted-slate py-4">No out-of-stock demand signals.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card-surface p-4 mt-3">
        <div class="d-flex align-items-start gap-2">
            <i class="bi bi-info-circle text-accent"></i>
            <div class="small text-muted-slate">Forecasting is based on historical MySQL sales &amp; search data (moving averages). This is an estimate, not a guarantee.</div>
        </div>
    </div>

    <script>
        window.demandChartData = @json($chartData);
    </script>
    @vite(['resources/js/charts-demand.js'])
</x-admin-layout>
