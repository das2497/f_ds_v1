<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AnalyticsUtmController extends Controller
{
    public function index(Request $request): View
    {
        $range = $request->string('range')->toString() ?: '30d';
        $channel = $request->string('channel')->toString() ?: 'all';

        [$from, $to] = $this->rangeDates($range);
        $priorLength = $from->diffInSeconds($to);
        $priorFrom = $from->copy()->subSeconds($priorLength);
        $priorTo = $from->copy();

        $scoped = fn () => AnalyticsEvent::query()
            ->when($channel !== 'all', fn ($q) => $q->where('utm_source', 'like', "%{$channel}%"));

        $current = $scoped()->whereBetween('created_at', [$from, $to]);
        $prior = $scoped()->whereBetween('created_at', [$priorFrom, $priorTo]);

        $sessions = (clone $current)->where('event_name', 'page_view')->distinct('session_id')->count('session_id');
        $priorSessions = (clone $prior)->where('event_name', 'page_view')->distinct('session_id')->count('session_id');

        $addToCarts = (clone $current)->where('event_name', 'add_to_cart')->count();
        $priorAddToCarts = (clone $prior)->where('event_name', 'add_to_cart')->count();

        $purchases = (clone $current)->where('event_name', 'purchase')->count();
        $priorPurchases = (clone $prior)->where('event_name', 'purchase')->count();

        $conversionRate = $sessions > 0 ? round(($purchases / $sessions) * 100, 1) : 0.0;
        $priorConversionRate = $priorSessions > 0 ? round(($priorPurchases / $priorSessions) * 100, 1) : 0.0;

        $funnel = [
            'Sessions' => $sessions,
            'Add to Cart' => $addToCarts,
            'Purchase' => $purchases,
        ];

        $campaignPerformance = (clone $current)
            ->selectRaw("
                COALESCE(utm_source, '(direct)') as source,
                COALESCE(utm_medium, '(none)') as medium,
                COALESCE(utm_campaign, '(not set)') as campaign,
                COUNT(DISTINCT CASE WHEN event_name = 'page_view' THEN session_id END) as sessions,
                COUNT(CASE WHEN event_name = 'add_to_cart' THEN 1 END) as add_to_cart,
                COUNT(CASE WHEN event_name = 'purchase' THEN 1 END) as purchases
            ")
            ->groupBy('source', 'medium', 'campaign')
            ->havingRaw('sessions > 0 OR purchases > 0')
            ->orderByDesc('purchases')
            ->get()
            ->map(fn ($row) => [
                'source' => $row->source,
                'medium' => $row->medium,
                'campaign' => $row->campaign,
                'sessions' => (int) $row->sessions,
                'add_to_cart' => (int) $row->add_to_cart,
                'purchases' => (int) $row->purchases,
                'conv_rate' => $row->sessions > 0 ? round(($row->purchases / $row->sessions) * 100, 1) : 0.0,
            ]);

        $topCampaigns = $campaignPerformance->sortByDesc('purchases')->take(4)->values();

        return view('admin.analytics-utm.index', [
            'range' => $range,
            'channel' => $channel,
            'sessions' => $sessions,
            'sessionsDelta' => $this->delta($sessions, $priorSessions),
            'addToCarts' => $addToCarts,
            'addToCartsDelta' => $this->delta($addToCarts, $priorAddToCarts),
            'purchases' => $purchases,
            'purchasesDelta' => $this->delta($purchases, $priorPurchases),
            'conversionRate' => $conversionRate,
            'conversionRateDelta' => round($conversionRate - $priorConversionRate, 1),
            'funnel' => $funnel,
            'topCampaigns' => $topCampaigns,
            'campaignPerformance' => $campaignPerformance,
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function rangeDates(string $range): array
    {
        return match ($range) {
            '7d' => [now()->subDays(7), now()],
            'quarter' => [now()->firstOfQuarter(), now()],
            default => [now()->subDays(30), now()],
        };
    }

    private function delta(int $current, int $prior): float
    {
        if ($prior === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $prior) / $prior) * 100, 1);
    }
}
