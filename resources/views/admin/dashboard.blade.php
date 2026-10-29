<x-admin-layout title="Main Dashboard">
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card__label">Gross Sales</div>
                <div class="stat-card__value">${{ number_format($grossSales, 2) }}</div>
                <div class="small text-muted-slate mt-1">All-time, paid orders</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card__label">Net Sales</div>
                <div class="stat-card__value">${{ number_format($netSales, 2) }}</div>
                <div class="small text-muted-slate mt-1">After refunds</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card__label">Total Orders</div>
                <div class="stat-card__value">{{ number_format($totalOrders) }}</div>
                <div class="small text-muted-slate mt-1">All-time, paid orders</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card__label">Paid vs Vendor Submitted</div>
                <div class="stat-card__value">{{ $paidCount }} / {{ $submittedCount }}</div>
                <div class="small text-muted-slate mt-1">Currently in the hold/fulfillment pipeline</div>
            </div>
        </div>
    </div>

    @php
        $deltaPct = function (array $series) {
            $first = $series[0] ?? 0;
            $last = $series[count($series) - 1] ?? 0;

            if ($first == 0) {
                return $last > 0 ? 100.0 : 0.0;
            }

            return round((($last - $first) / $first) * 100, 1);
        };
        $salesDelta = $deltaPct($trend['sales']);
        $ordersDelta = $deltaPct($trend['orders']);
        $sessionsDelta = $deltaPct($trend['sessions']);
    @endphp
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="chart-card">
                <div class="chart-card__subtitle">GROSS SALES &middot; LAST 14 DAYS</div>
                <div class="d-flex align-items-baseline gap-2 mb-1">
                    <div class="chart-card__title fs-4">${{ number_format(array_sum($trend['sales']), 0) }}</div>
                    <span class="stat-delta {{ $salesDelta >= 0 ? 'up' : 'down' }}"><i class="bi bi-arrow-{{ $salesDelta >= 0 ? 'up' : 'down' }}-short"></i>{{ abs($salesDelta) }}%</span>
                </div>
                <div style="height:60px"><canvas id="chartSales"></canvas></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="chart-card">
                <div class="chart-card__subtitle">ORDERS &middot; LAST 14 DAYS</div>
                <div class="d-flex align-items-baseline gap-2 mb-1">
                    <div class="chart-card__title fs-4">{{ number_format(array_sum($trend['orders'])) }}</div>
                    <span class="stat-delta {{ $ordersDelta >= 0 ? 'up' : 'down' }}"><i class="bi bi-arrow-{{ $ordersDelta >= 0 ? 'up' : 'down' }}-short"></i>{{ abs($ordersDelta) }}%</span>
                </div>
                <div style="height:60px"><canvas id="chartOrders"></canvas></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="chart-card">
                <div class="chart-card__subtitle">SITE SESSIONS &middot; LAST 14 DAYS</div>
                <div class="d-flex align-items-baseline gap-2 mb-1">
                    <div class="chart-card__title fs-4">{{ number_format(array_sum($trend['sessions'])) }}</div>
                    <span class="stat-delta {{ $sessionsDelta >= 0 ? 'up' : 'down' }}"><i class="bi bi-arrow-{{ $sessionsDelta >= 0 ? 'up' : 'down' }}-short"></i>{{ abs($sessionsDelta) }}%</span>
                </div>
                <div style="height:60px"><canvas id="chartSessions"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="table-admin p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Recent Orders</h6>
                    <a href="{{ route('admin.orders') }}" class="small text-accent text-decoration-none">View All <i class="bi bi-arrow-right"></i></a>
                </div>
                <table class="table table-admin align-middle mb-0">
                    <thead>
                        <tr><th>Order Ref</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($recentOrders as $order)
                            @php $display = $order->displayStatus(); @endphp
                            <tr>
                                <td>{{ $order->order_number }}</td>
                                <td>{{ $order->shipping_name }}</td>
                                <td>${{ number_format($order->total, 2) }}</td>
                                <td><span class="{{ $display['class'] }}">{{ $display['label'] }}</span></td>
                                <td>{{ $order->created_at->format('M j, Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted-slate py-3">No orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="table-admin p-3 h-100">
                <h6 class="mb-3">Demand &amp; Marketing Snapshot</h6>
                <div class="row-line"><span class="text-muted-slate">Top Campaign</span><span class="fw-semibold">{{ $topCampaign->utm_campaign ?? 'No data yet' }}</span></div>
                <div class="row-line"><span class="text-muted-slate">Conversion Rate</span><span class="fw-semibold">{{ $sessions30d > 0 ? round(($purchases30d / $sessions30d) * 100, 1) : 0 }}%</span></div>
                <div class="row-line"><span class="text-muted-slate">Trending Product</span><span class="fw-semibold">{{ $trendingProduct->product->title ?? 'No data yet' }}</span></div>
                <div class="row-line mb-0"><span class="text-muted-slate">Out of Stock Demand</span><span class="fw-semibold {{ $outOfStockDemandCount > 0 ? 'text-danger' : '' }}">{{ $outOfStockDemandCount }} items</span></div>
                <div class="d-flex gap-2 mt-3">
                    <a href="{{ route('admin.analytics-demand') }}" class="btn btn-sm btn-outline-secondary flex-grow-1">Demand Report</a>
                    <a href="{{ route('admin.analytics-utm') }}" class="btn btn-sm btn-outline-secondary flex-grow-1">Marketing Report</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.dashboardTrend = @json($trend);
    </script>
    @vite(['resources/js/charts-dashboard.js'])
</x-admin-layout>
