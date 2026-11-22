<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics\Ga4ReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Throwable;

class Ga4DashboardController extends Controller
{
    public function __construct(private readonly Ga4ReportingService $ga4) {}

    public function index(Request $request): View
    {
        $range = $request->string('range')->toString() ?: '30d';
        [$from, $to] = $this->rangeDates($range);

        if (! $this->ga4->configured()) {
            return view('admin.analytics-ga4.index', [
                'range' => $range,
                'configured' => false,
                'error' => null,
            ]);
        }

        try {
            $priorLength = $from->diffInSeconds($to);
            $overview = $this->ga4->overview($from, $to);
            $priorOverview = $this->ga4->overview($from->copy()->subSeconds($priorLength), $from->copy());

            return view('admin.analytics-ga4.index', [
                'range' => $range,
                'configured' => true,
                'error' => null,
                'overview' => $overview,
                'overviewDelta' => [
                    'totalUsers' => $this->delta($overview['totalUsers'], $priorOverview['totalUsers']),
                    'sessions' => $this->delta($overview['sessions'], $priorOverview['sessions']),
                    'screenPageViews' => $this->delta($overview['screenPageViews'], $priorOverview['screenPageViews']),
                    'keyEvents' => $this->delta($overview['keyEvents'], $priorOverview['keyEvents']),
                ],
                'dailyTrend' => $this->ga4->dailyTrend($from, $to),
                'trafficByChannel' => $this->ga4->trafficByChannel($from, $to),
                'byDevice' => $this->ga4->byDevice($from, $to),
                'byCountry' => $this->ga4->byCountry($from, $to),
                'topPages' => $this->ga4->topPages($from, $to),
            ]);
        } catch (Throwable $e) {
            report($e);

            return view('admin.analytics-ga4.index', [
                'range' => $range,
                'configured' => true,
                'error' => 'Could not reach the GA4 Data API. Check the property ID and service account access in Gateway Settings.',
            ]);
        }
    }

    /**
     * Polled client-side every few seconds for the "Live Now" indicator —
     * kept separate from index() so a slow/broken GA4 call here never
     * breaks the full dashboard page, just the live badge.
     */
    public function realtime(): JsonResponse
    {
        if (! $this->ga4->configured()) {
            return response()->json(['configured' => false]);
        }

        try {
            return response()->json([
                'configured' => true,
                'activeUsers' => $this->ga4->realtimeActiveUsers(),
                'topPages' => $this->ga4->realtimeTopPages(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['configured' => true, 'error' => true], 502);
        }
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
