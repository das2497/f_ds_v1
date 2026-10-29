<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsDemandLog;
use App\Models\AnalyticsEvent;
use App\Models\Order;
use App\Models\Refund;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $soldStatuses = ['paid', 'refunded'];

        $grossSales = (float) Order::whereIn('status', $soldStatuses)->sum('total');
        $netSales = $grossSales - (float) Refund::where('status', 'succeeded')->sum('amount');
        $totalOrders = Order::whereIn('status', $soldStatuses)->count();
        $paidCount = Order::where('status', 'paid')->count();
        $submittedCount = Order::where('status', 'paid')
            ->whereHas('vendorOrders', fn ($q) => $q->where('status', 'submitted'))
            ->count();

        $recentOrders = Order::with('vendorOrders')->latest()->limit(5)->get();

        $days = collect(range(13, 0))->map(fn ($i) => now()->subDays($i)->startOfDay());

        $salesByDay = Order::whereIn('status', $soldStatuses)
            ->where('created_at', '>=', $days->first())
            ->selectRaw('DATE(created_at) as d, SUM(total) as total')
            ->groupBy('d')->pluck('total', 'd');

        $ordersByDay = Order::whereIn('status', $soldStatuses)
            ->where('created_at', '>=', $days->first())
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')->pluck('c', 'd');

        $sessionsByDay = AnalyticsEvent::where('event_name', 'page_view')
            ->where('created_at', '>=', $days->first())
            ->selectRaw('DATE(created_at) as d, COUNT(DISTINCT session_id) as c')
            ->groupBy('d')->pluck('c', 'd');

        $trend = [
            'labels' => $days->map(fn (Carbon $d) => $d->format('M j'))->all(),
            'sales' => $days->map(fn (Carbon $d) => (float) ($salesByDay[$d->toDateString()] ?? 0))->all(),
            'orders' => $days->map(fn (Carbon $d) => (int) ($ordersByDay[$d->toDateString()] ?? 0))->all(),
            'sessions' => $days->map(fn (Carbon $d) => (int) ($sessionsByDay[$d->toDateString()] ?? 0))->all(),
        ];

        $topCampaign = AnalyticsEvent::where('event_name', 'purchase')
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('utm_campaign')
            ->selectRaw('utm_campaign, COUNT(*) as c')
            ->groupBy('utm_campaign')
            ->orderByDesc('c')
            ->first();

        $sessions30d = AnalyticsEvent::where('event_name', 'page_view')
            ->where('created_at', '>=', now()->subDays(30))
            ->distinct('session_id')->count('session_id');
        $purchases30d = AnalyticsEvent::where('event_name', 'purchase')
            ->where('created_at', '>=', now()->subDays(30))->count();

        $trendingProduct = AnalyticsDemandLog::where('event_type', 'view_item')
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('product_id')
            ->selectRaw('product_id, COUNT(*) as c')
            ->groupBy('product_id')
            ->orderByDesc('c')
            ->with('product')
            ->first();

        $outOfStockDemandCount = AnalyticsDemandLog::whereIn('product_id', function ($q) {
            $q->select('product_id')->from('product_variants')->where('stock_status', 'out_of_stock');
        })->where('created_at', '>=', now()->subDays(30))->distinct('product_id')->count('product_id');

        return view('admin.dashboard', compact(
            'grossSales', 'netSales', 'totalOrders', 'paidCount', 'submittedCount', 'recentOrders',
            'trend', 'topCampaign', 'sessions30d', 'purchases30d', 'trendingProduct', 'outOfStockDemandCount'
        ));
    }
}
