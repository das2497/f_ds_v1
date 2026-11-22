<x-admin-layout title="Google Analytics">
    @if (! $configured)
        <div class="card-surface p-4 mb-3 text-center">
            <i class="bi bi-google text-accent fs-3 d-block mb-2"></i>
            <div class="fw-semibold mb-1">GA4 reporting isn't connected yet</div>
            <div class="small text-muted-slate mb-3">
                Add a GA4 Property ID and a service account JSON key in Gateway Settings — then this page will pull live traffic, device and conversion data straight from Google Analytics.
            </div>
            @role('super-admin')
                <a href="{{ route('admin.settings-gateways') }}" class="btn btn-sm btn-accent">
                    <i class="bi bi-key"></i> Set Up GA4 Reporting
                </a>
            @endrole
        </div>
    @elseif ($error)
        <div class="card-surface p-4 mb-3 text-center">
            <i class="bi bi-exclamation-triangle text-warning fs-3 d-block mb-2"></i>
            <div class="fw-semibold mb-1">Couldn't load Google Analytics</div>
            <div class="small text-muted-slate mb-3">{{ $error }}</div>
            @role('super-admin')
                <a href="{{ route('admin.settings-gateways') }}" class="btn btn-sm btn-accent">
                    <i class="bi bi-key"></i> Check Gateway Settings
                </a>
            @endrole
        </div>
    @else
        @php
            $chartData = [
                'dailyTrend' => $dailyTrend,
                'trafficByChannel' => $trafficByChannel,
                'byDevice' => $byDevice,
            ];
        @endphp

        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <form method="GET" class="d-flex gap-2 flex-wrap">
                <select name="range" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                    <option value="30d" @selected($range === '30d')>Last 30 Days</option>
                    <option value="7d" @selected($range === '7d')>Last 7 Days</option>
                    <option value="quarter" @selected($range === 'quarter')>This Quarter</option>
                </select>
            </form>

            <div class="small text-muted-slate d-flex align-items-center gap-2" id="ga4-live-now" title="Active users in the last 30 minutes">
                <span class="live-dot"></span>
                <span>Live now: <strong id="ga4-live-count">—</strong> active user(s)</span>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-card__label">Users</div>
                    <div class="stat-card__value">{{ number_format($overview['totalUsers']) }}</div>
                    <span class="stat-delta {{ $overviewDelta['totalUsers'] >= 0 ? 'up' : 'down' }}"><i class="bi bi-arrow-{{ $overviewDelta['totalUsers'] >= 0 ? 'up' : 'down' }}-short"></i>{{ abs($overviewDelta['totalUsers']) }}% vs prior period</span>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-card__label">Sessions</div>
                    <div class="stat-card__value">{{ number_format($overview['sessions']) }}</div>
                    <span class="stat-delta {{ $overviewDelta['sessions'] >= 0 ? 'up' : 'down' }}"><i class="bi bi-arrow-{{ $overviewDelta['sessions'] >= 0 ? 'up' : 'down' }}-short"></i>{{ abs($overviewDelta['sessions']) }}% vs prior period</span>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-card__label">Page Views</div>
                    <div class="stat-card__value">{{ number_format($overview['screenPageViews']) }}</div>
                    <span class="stat-delta {{ $overviewDelta['screenPageViews'] >= 0 ? 'up' : 'down' }}"><i class="bi bi-arrow-{{ $overviewDelta['screenPageViews'] >= 0 ? 'up' : 'down' }}-short"></i>{{ abs($overviewDelta['screenPageViews']) }}% vs prior period</span>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-card__label">Key Events</div>
                    <div class="stat-card__value">{{ number_format($overview['keyEvents']) }}</div>
                    <span class="stat-delta {{ $overviewDelta['keyEvents'] >= 0 ? 'up' : 'down' }}"><i class="bi bi-arrow-{{ $overviewDelta['keyEvents'] >= 0 ? 'up' : 'down' }}-short"></i>{{ abs($overviewDelta['keyEvents']) }}% vs prior period</span>
                </div>
            </div>
        </div>

        <div class="small text-muted-slate mb-4">
            Avg. engagement time <strong>{{ $overview['averageSessionDuration'] }}s</strong> &middot; Bounce rate <strong>{{ $overview['bounceRate'] }}%</strong>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-7">
                <div class="chart-card h-100">
                    <div class="chart-card__title mb-1">Users &amp; Sessions Over Time</div>
                    <div class="chart-card__subtitle mb-2">Selected period</div>
                    <div style="height:220px"><canvas id="chartTrend"></canvas></div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="chart-card h-100">
                    <div class="chart-card__title mb-1">Sessions by Device</div>
                    <div class="chart-card__subtitle mb-2">Selected period</div>
                    <div style="height:220px"><canvas id="chartDevice"></canvas></div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-7">
                <div class="chart-card h-100">
                    <div class="chart-card__title mb-1">Traffic by Channel</div>
                    <div class="chart-card__subtitle mb-2">Sessions by acquisition channel</div>
                    <div style="height:220px"><canvas id="chartChannel"></canvas></div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="table-admin p-3 h-100">
                    <h6 class="mb-3">Top Countries</h6>
                    <table class="table table-admin align-middle mb-0">
                        <thead><tr><th>Country</th><th>Users</th></tr></thead>
                        <tbody>
                            @forelse ($byCountry as $row)
                                <tr>
                                    <td>{{ $row['country'] }}</td>
                                    <td>{{ number_format($row['totalUsers']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted-slate py-4">No data in this period yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="table-admin p-3">
            <h6 class="mb-3">Top Pages <span class="small text-muted-slate fw-normal">(by page views)</span></h6>
            <table class="table table-admin align-middle mb-0">
                <thead><tr><th>Page</th><th>Page Views</th></tr></thead>
                <tbody>
                    @forelse ($topPages as $row)
                        <tr>
                            <td>{{ $row['path'] }}</td>
                            <td>{{ number_format($row['screenPageViews']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-muted-slate py-4">No data in this period yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <script>
            window.ga4ChartData = @json($chartData);
            window.ga4RealtimeUrl = @json(route('admin.analytics-ga4.realtime'));
        </script>
        @vite(['resources/js/charts-ga4.js'])
    @endif
</x-admin-layout>
