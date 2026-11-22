<?php

namespace App\Services\Analytics;

use App\Models\AppSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads traffic/behavior/demographic data out of the GA4 property itself
 * (Google Analytics Data API v1beta `runReport`) for the admin dashboard —
 * a separate concern from Ga4MeasurementProtocolService, which only
 * *sends* purchase/refund hits into GA4. Google never serves query results
 * for a hit "live" through the Data API (there's a processing lag), so
 * this is for the trends/breakdowns GA4 computes, not the moment a hit
 * lands.
 *
 * The service-account JWT bearer token is hand-signed with openssl, same
 * as GoogleSheetsService — see that class's docblock for why this avoids
 * google/apiclient.
 */
class Ga4ReportingService
{
    private const BASE_URL = 'https://analyticsdata.googleapis.com/v1beta';

    public function configured(): bool
    {
        return filled($this->propertyId()) && filled($this->credentialsConfig());
    }

    /**
     * @return array{totalUsers: int, sessions: int, screenPageViews: int, averageSessionDuration: float, bounceRate: float, keyEvents: int}
     */
    public function overview(Carbon $from, Carbon $to): array
    {
        $row = $this->runReport($from, $to, [], [
            'totalUsers', 'sessions', 'screenPageViews', 'averageSessionDuration', 'bounceRate', 'keyEvents',
        ])->first();

        return [
            'totalUsers' => (int) ($row['totalUsers'] ?? 0),
            'sessions' => (int) ($row['sessions'] ?? 0),
            'screenPageViews' => (int) ($row['screenPageViews'] ?? 0),
            'averageSessionDuration' => round((float) ($row['averageSessionDuration'] ?? 0), 1),
            'bounceRate' => round((float) ($row['bounceRate'] ?? 0) * 100, 1),
            'keyEvents' => (int) ($row['keyEvents'] ?? 0),
        ];
    }

    /**
     * @return Collection<int, array{date: string, totalUsers: int, sessions: int}>
     */
    public function dailyTrend(Carbon $from, Carbon $to): Collection
    {
        return $this->runReport($from, $to, ['date'], ['totalUsers', 'sessions'], orderByDimension: 'date')
            ->map(fn ($row) => [
                'date' => Carbon::createFromFormat('Ymd', $row['date'])->format('M j'),
                'totalUsers' => (int) $row['totalUsers'],
                'sessions' => (int) $row['sessions'],
            ]);
    }

    /**
     * @return Collection<int, array{channel: string, sessions: int, totalUsers: int, keyEvents: int}>
     */
    public function trafficByChannel(Carbon $from, Carbon $to, int $limit = 10): Collection
    {
        return $this->runReport($from, $to, ['sessionDefaultChannelGroup'], ['sessions', 'totalUsers', 'keyEvents'], orderByMetric: 'sessions', limit: $limit)
            ->map(fn ($row) => [
                'channel' => $row['sessionDefaultChannelGroup'] ?: '(not set)',
                'sessions' => (int) $row['sessions'],
                'totalUsers' => (int) $row['totalUsers'],
                'keyEvents' => (int) $row['keyEvents'],
            ]);
    }

    /**
     * @return Collection<int, array{device: string, sessions: int, totalUsers: int}>
     */
    public function byDevice(Carbon $from, Carbon $to): Collection
    {
        return $this->runReport($from, $to, ['deviceCategory'], ['sessions', 'totalUsers'], orderByMetric: 'sessions')
            ->map(fn ($row) => [
                'device' => ucfirst($row['deviceCategory'] ?: 'unknown'),
                'sessions' => (int) $row['sessions'],
                'totalUsers' => (int) $row['totalUsers'],
            ]);
    }

    /**
     * @return Collection<int, array{country: string, totalUsers: int}>
     */
    public function byCountry(Carbon $from, Carbon $to, int $limit = 8): Collection
    {
        return $this->runReport($from, $to, ['country'], ['totalUsers'], orderByMetric: 'totalUsers', limit: $limit)
            ->map(fn ($row) => [
                'country' => $row['country'] ?: '(not set)',
                'totalUsers' => (int) $row['totalUsers'],
            ]);
    }

    /**
     * @return Collection<int, array{path: string, screenPageViews: int}>
     */
    public function topPages(Carbon $from, Carbon $to, int $limit = 10): Collection
    {
        return $this->runReport($from, $to, ['pagePath'], ['screenPageViews'], orderByMetric: 'screenPageViews', limit: $limit)
            ->map(fn ($row) => [
                'path' => $row['pagePath'],
                'screenPageViews' => (int) $row['screenPageViews'],
            ]);
    }

    /**
     * Active users right now — GA4's Realtime API counts activity from
     * roughly the last 30 minutes, with no processing lag (unlike
     * runReport above), so this is what a "Live Now" indicator polls.
     */
    public function realtimeActiveUsers(): int
    {
        $row = $this->runRealtimeReport([], ['activeUsers'])->first();

        return (int) ($row['activeUsers'] ?? 0);
    }

    /**
     * @return Collection<int, array{page: string, activeUsers: int}>
     */
    public function realtimeTopPages(int $limit = 5): Collection
    {
        return $this->runRealtimeReport(['unifiedScreenName'], ['activeUsers'], orderByMetric: 'activeUsers', limit: $limit)
            ->map(fn ($row) => [
                'page' => $row['unifiedScreenName'] ?: '(not set)',
                'activeUsers' => (int) $row['activeUsers'],
            ]);
    }

    /**
     * @param  array<int, string>  $dimensions
     * @param  array<int, string>  $metrics
     * @return Collection<int, array<string, string>> each row keyed by dimension/metric name
     */
    private function runReport(Carbon $from, Carbon $to, array $dimensions, array $metrics, ?string $orderByDimension = null, ?string $orderByMetric = null, ?int $limit = null): Collection
    {
        $body = [
            'dateRanges' => [['startDate' => $from->toDateString(), 'endDate' => $to->toDateString()]],
            ...$this->reportBody($dimensions, $metrics, $orderByDimension, $orderByMetric, $limit),
        ];

        return $this->runCollectedReport('runReport', $body);
    }

    /**
     * @param  array<int, string>  $dimensions
     * @param  array<int, string>  $metrics
     * @return Collection<int, array<string, string>> each row keyed by dimension/metric name
     */
    private function runRealtimeReport(array $dimensions, array $metrics, ?string $orderByMetric = null, ?int $limit = null): Collection
    {
        return $this->runCollectedReport('runRealtimeReport', $this->reportBody($dimensions, $metrics, null, $orderByMetric, $limit));
    }

    /**
     * @param  array<int, string>  $dimensions
     * @param  array<int, string>  $metrics
     * @return array<string, mixed>
     */
    private function reportBody(array $dimensions, array $metrics, ?string $orderByDimension, ?string $orderByMetric, ?int $limit): array
    {
        $body = [
            'dimensions' => array_map(fn ($name) => ['name' => $name], $dimensions),
            'metrics' => array_map(fn ($name) => ['name' => $name], $metrics),
        ];

        if ($orderByDimension) {
            $body['orderBys'] = [['dimension' => ['dimensionName' => $orderByDimension]]];
        } elseif ($orderByMetric) {
            $body['orderBys'] = [['metric' => ['metricName' => $orderByMetric], 'desc' => true]];
        }

        if ($limit) {
            $body['limit'] = $limit;
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return Collection<int, array<string, string>> each row keyed by dimension/metric name
     */
    private function runCollectedReport(string $endpoint, array $body): Collection
    {
        if (! $this->configured()) {
            throw new RuntimeException('GA4 reporting is not configured.');
        }

        $response = Http::withToken($this->accessToken())
            ->timeout(15)
            ->post(self::BASE_URL."/properties/{$this->propertyId()}:{$endpoint}", $body)
            ->throw();

        $dimensionNames = collect($response->json('dimensionHeaders', []))->pluck('name');
        $metricNames = collect($response->json('metricHeaders', []))->pluck('name');

        return collect($response->json('rows', []))->map(function ($row) use ($dimensionNames, $metricNames) {
            $values = [];

            foreach ($dimensionNames as $i => $name) {
                $values[$name] = $row['dimensionValues'][$i]['value'] ?? '';
            }

            foreach ($metricNames as $i => $name) {
                $values[$name] = $row['metricValues'][$i]['value'] ?? '0';
            }

            return $values;
        });
    }

    private function accessToken(): string
    {
        return Cache::remember('google_analytics_access_token', 3000, function () {
            $credentials = $this->credentialsConfig();

            if ($credentials === null) {
                throw new RuntimeException('GA4 reporting credentials are not configured.');
            }

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->signedJwt($credentials),
            ])->throw();

            return $response->json('access_token');
        });
    }

    /**
     * @param  array<string, mixed>  $credentials  the decoded service account JSON
     */
    private function signedJwt(array $credentials): string
    {
        $now = time();

        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $privateKey = openssl_pkey_get_private($credentials['private_key']);

        if ($privateKey === false) {
            throw new RuntimeException('GA4 reporting service account private key is invalid.');
        }

        openssl_sign("{$header}.{$claims}", $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return "{$header}.{$claims}.".$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function propertyId(): ?string
    {
        return AppSetting::get('ga4.property_id') ?: config('services.ga4.property_id');
    }

    /**
     * @return array<string, mixed>|null the decoded service account JSON
     */
    private function credentialsConfig(): ?array
    {
        $json = AppSetting::get('ga4.reporting_credentials_json');

        if (blank($json)) {
            $path = config('services.ga4.reporting_credentials_path');
            $json = $path && is_file(base_path($path)) ? file_get_contents(base_path($path)) : null;
        }

        if (blank($json)) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) && filled($decoded['private_key'] ?? null) && filled($decoded['client_email'] ?? null)
            ? $decoded
            : null;
    }
}
