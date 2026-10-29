<?php

namespace App\Http\Middleware;

use App\Services\Analytics\AttributionService;
use App\Services\Analytics\EventTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every storefront request: keeps the analytics session cookie and
 * first/last-touch UTM cookies current, then logs a `page_view` event.
 * Skipped for admin/auth/webhook/asset routes — those aren't marketing
 * landing pages and would just add noise to the reports.
 */
class CaptureUtmAttribution
{
    public function __construct(
        private readonly AttributionService $attribution,
        private readonly EventTracker $tracker,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || $this->isExcluded($request)) {
            return $next($request);
        }

        $this->attribution->ensureSessionCookie($request);
        $this->attribution->captureFromRequest($request);
        $this->attribution->captureTikTokClickId($request);

        $response = $next($request);

        $this->tracker->track($request, 'page_view');

        return $response;
    }

    private function isExcluded(Request $request): bool
    {
        return $request->is('admin/*')
            || $request->is('stripe/*')
            || $request->is('build/*')
            || $request->is('storage/*')
            || $request->ajax()
            || $request->expectsJson();
    }
}
