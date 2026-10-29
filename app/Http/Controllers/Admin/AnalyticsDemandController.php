<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsDemandLog;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AnalyticsDemandController extends Controller
{
    public function index(): View
    {
        return view('admin.analytics-demand.index', [
            'trending' => $this->trendingProducts(),
            'demandByState' => $this->demandByState(),
            'topKeywords' => $this->topKeywords(),
            'outOfStockDemand' => $this->outOfStockDemand(),
        ]);
    }

    /**
     * Week-over-week growth in demand_logs (views + searches matched by
     * title) — a PHP-only moving-average-style estimate, not a forecast
     * (1_0.txt §8: no FastAPI module in this version).
     */
    private function trendingProducts()
    {
        $thisWeek = AnalyticsDemandLog::where('event_type', 'view_item')
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('product_id')
            ->selectRaw('product_id, COUNT(*) as demand_score')
            ->groupBy('product_id')
            ->orderByDesc('demand_score')
            ->limit(5)
            ->get()
            ->keyBy('product_id');

        $lastWeek = AnalyticsDemandLog::where('event_type', 'view_item')
            ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])
            ->whereIn('product_id', $thisWeek->keys())
            ->selectRaw('product_id, COUNT(*) as demand_score')
            ->groupBy('product_id')
            ->pluck('demand_score', 'product_id');

        $products = Product::whereIn('id', $thisWeek->keys())->get()->keyBy('id');

        return $thisWeek->map(function ($row) use ($lastWeek, $products) {
            $prior = (int) ($lastWeek[$row->product_id] ?? 0);
            $growth = $prior > 0
                ? round((($row->demand_score - $prior) / $prior) * 100)
                : ($row->demand_score > 0 ? 100 : 0);

            return [
                'title' => $products[$row->product_id]->title ?? 'Unknown product',
                'score' => (int) $row->demand_score,
                'growth' => $growth,
            ];
        })->values();
    }

    private function demandByState()
    {
        return Order::whereIn('status', ['paid', 'refunded'])
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('shipping_state, COUNT(*) as orders')
            ->groupBy('shipping_state')
            ->orderByDesc('orders')
            ->limit(6)
            ->get();
    }

    private function topKeywords()
    {
        return AnalyticsDemandLog::where('event_type', 'search')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('search_term, COUNT(*) as searches, AVG(results_count) as avg_results')
            ->groupBy('search_term')
            ->orderByDesc('searches')
            ->limit(10)
            ->get();
    }

    /**
     * Product-level approximation: any out-of-stock variant on a product
     * that customers have recently viewed/searched for is flagged, bucketed
     * by recent demand volume. Size/color-level demand isn't tracked, so
     * this can't pin the signal to the exact missing variant.
     */
    private function outOfStockDemand()
    {
        $outOfStockProductIds = DB::table('product_variants')
            ->where('stock_status', 'out_of_stock')
            ->distinct()
            ->pluck('product_id');

        $demandCounts = AnalyticsDemandLog::whereIn('product_id', $outOfStockProductIds)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('product_id, COUNT(*) as demand_count')
            ->groupBy('product_id')
            ->orderByDesc('demand_count')
            ->limit(10)
            ->get();

        $products = Product::whereIn('id', $demandCounts->pluck('product_id'))->get()->keyBy('id');

        return $demandCounts->map(fn ($row) => [
            'title' => $products[$row->product_id]->title ?? 'Unknown product',
            'level' => match (true) {
                $row->demand_count >= 20 => 'High',
                $row->demand_count >= 8 => 'Medium',
                default => 'Low',
            },
        ])->values();
    }
}
