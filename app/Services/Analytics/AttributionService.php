<?php

namespace App\Services\Analytics;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * First-touch is captured once and never overwritten; last-touch is
 * refreshed on every request that arrives with UTM parameters. Both are
 * kept as separate cookies so an order can snapshot them independently
 * (1_0.txt §7 — "First-touch සහ last-touch attribution වෙන වෙනම සුරකින්න").
 */
class AttributionService
{
    private const SESSION_COOKIE = 'analytics_sid';

    private const FIRST_TOUCH_COOKIE = 'utm_first';

    private const LAST_TOUCH_COOKIE = 'utm_last';

    private const TTCLID_COOKIE = 'ttclid';

    private const TTL_MINUTES = 60 * 24 * 365;

    public function sessionId(Request $request): string
    {
        return $request->cookie(self::SESSION_COOKIE) ?? (string) Str::uuid();
    }

    public function ensureSessionCookie(Request $request): void
    {
        if (! $request->cookie(self::SESSION_COOKIE)) {
            Cookie::queue(self::SESSION_COOKIE, $this->sessionId($request), self::TTL_MINUTES);
        }
    }

    public function captureFromRequest(Request $request): void
    {
        $utm = $this->utmFromQuery($request);

        if (! $utm) {
            return;
        }

        if (! $request->cookie(self::FIRST_TOUCH_COOKIE)) {
            Cookie::queue(self::FIRST_TOUCH_COOKIE, json_encode($utm), self::TTL_MINUTES);
        }

        Cookie::queue(self::LAST_TOUCH_COOKIE, json_encode($utm), self::TTL_MINUTES);
    }

    /**
     * `ttclid` only ever arrives as a query parameter on the ad-click
     * landing page — persisted here so it's still available by the time
     * the customer reaches checkout, several pages later.
     */
    public function captureTikTokClickId(Request $request): void
    {
        if ($request->filled('ttclid')) {
            Cookie::queue(self::TTCLID_COOKIE, $request->string('ttclid')->toString(), self::TTL_MINUTES);
        }
    }

    public function tiktokClickId(Request $request): ?string
    {
        return $request->cookie(self::TTCLID_COOKIE) ?: ($request->filled('ttclid') ? $request->string('ttclid')->toString() : null);
    }

    /**
     * The touch used for event-level reporting (sessions, add-to-cart,
     * purchases on the UTM Attribution table) — last-touch, the industry
     * default for conversion credit.
     *
     * @return array{source: ?string, medium: ?string, campaign: ?string, ad_id: ?string}
     */
    public function currentTouch(Request $request): array
    {
        return $this->decode($request->cookie(self::LAST_TOUCH_COOKIE)) ?? $this->emptyTouch();
    }

    /**
     * @return array{source: ?string, medium: ?string, campaign: ?string, ad_id: ?string}
     */
    public function firstTouch(Request $request): array
    {
        return $this->decode($request->cookie(self::FIRST_TOUCH_COOKIE)) ?? $this->emptyTouch();
    }

    /**
     * @return array{source: ?string, medium: ?string, campaign: ?string, ad_id: ?string}
     */
    public function lastTouch(Request $request): array
    {
        return $this->decode($request->cookie(self::LAST_TOUCH_COOKIE)) ?? $this->emptyTouch();
    }

    /**
     * @return array{source: string, medium: string, campaign: string, ad_id: ?string}|null
     */
    private function utmFromQuery(Request $request): ?array
    {
        if (! $request->filled('utm_source') && ! $request->filled('utm_campaign')) {
            return null;
        }

        return [
            'source' => $request->string('utm_source')->toString() ?: '(direct)',
            'medium' => $request->string('utm_medium')->toString() ?: '(none)',
            'campaign' => $request->string('utm_campaign')->toString() ?: '(not set)',
            'ad_id' => $request->filled('utm_ad_id') ? $request->string('utm_ad_id')->toString() : null,
        ];
    }

    /**
     * @return array{source: ?string, medium: ?string, campaign: ?string, ad_id: ?string}|null
     */
    private function decode(?string $raw): ?array
    {
        if (! $raw) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array{source: null, medium: null, campaign: null, ad_id: null}
     */
    private function emptyTouch(): array
    {
        return ['source' => null, 'medium' => null, 'campaign' => null, 'ad_id' => null];
    }
}
