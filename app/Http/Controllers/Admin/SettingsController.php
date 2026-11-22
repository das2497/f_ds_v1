<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const KEYS = [
        'stripe.key', 'stripe.secret', 'stripe.webhook_secret',
        'printful.api_key', 'printful.store_id',
        'cj.api_key',
        'ollama.api_key', 'ollama.model',
        'ga4.measurement_id', 'ga4.api_secret', 'ga4.property_id', 'ga4.reporting_credentials_json',
        'meta.pixel_id', 'meta.access_token',
        'tiktok.pixel_code', 'tiktok.access_token',
        'google.client_id', 'google.client_secret',
        'google_sheets.spreadsheet_id', 'google_sheets.credentials_json', 'google_sheets.sync_token',
        'aliexpress_extension.token',
    ];

    public function index(): View
    {
        $settings = collect(self::KEYS)->mapWithKeys(fn ($key) => [$key => AppSetting::get($key)]);

        return view('admin.settings-gateways.index', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'stripe_key' => ['nullable', 'string', 'max:255'],
            'stripe_secret' => ['nullable', 'string', 'max:255'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:255'],
            'printful_api_key' => ['nullable', 'string', 'max:255'],
            'printful_store_id' => ['nullable', 'string', 'max:255'],
            'cj_api_key' => ['nullable', 'string', 'max:255'],
            'ollama_api_key' => ['nullable', 'string', 'max:255'],
            'ollama_model' => ['nullable', 'string', 'max:255'],
            'ga4_measurement_id' => ['nullable', 'string', 'max:255'],
            'ga4_api_secret' => ['nullable', 'string', 'max:255'],
            'ga4_property_id' => ['nullable', 'string', 'max:255'],
            'ga4_reporting_credentials_json' => ['nullable', 'json', function ($attribute, $value, $fail) {
                $decoded = json_decode((string) $value, true);
                if (filled($value) && (! is_array($decoded) || blank($decoded['private_key'] ?? null) || blank($decoded['client_email'] ?? null))) {
                    $fail('That doesn\'t look like a Google service account key — it needs "client_email" and "private_key".');
                }
            }],
            'meta_pixel_id' => ['nullable', 'string', 'max:255'],
            'meta_access_token' => ['nullable', 'string', 'max:255'],
            'tiktok_pixel_code' => ['nullable', 'string', 'max:255'],
            'tiktok_access_token' => ['nullable', 'string', 'max:255'],
            'google_client_id' => ['nullable', 'string', 'max:255'],
            'google_client_secret' => ['nullable', 'string', 'max:255'],
            'google_sheets_spreadsheet_id' => ['nullable', 'string', 'max:255'],
            'google_sheets_credentials_json' => ['nullable', 'json', function ($attribute, $value, $fail) {
                $decoded = json_decode((string) $value, true);
                if (filled($value) && (! is_array($decoded) || blank($decoded['private_key'] ?? null) || blank($decoded['client_email'] ?? null))) {
                    $fail('That doesn\'t look like a Google service account key — it needs "client_email" and "private_key".');
                }
            }],
            'google_sheets_sync_token' => ['nullable', 'string', 'max:255'],
            'aliexpress_extension_token' => ['nullable', 'string', 'max:255'],
        ]);

        $changed = [];

        $map = [
            'stripe_key' => 'stripe.key',
            'stripe_secret' => 'stripe.secret',
            'stripe_webhook_secret' => 'stripe.webhook_secret',
            'printful_api_key' => 'printful.api_key',
            'printful_store_id' => 'printful.store_id',
            'cj_api_key' => 'cj.api_key',
            'ollama_api_key' => 'ollama.api_key',
            'ollama_model' => 'ollama.model',
            'ga4_measurement_id' => 'ga4.measurement_id',
            'ga4_api_secret' => 'ga4.api_secret',
            'ga4_property_id' => 'ga4.property_id',
            'ga4_reporting_credentials_json' => 'ga4.reporting_credentials_json',
            'meta_pixel_id' => 'meta.pixel_id',
            'meta_access_token' => 'meta.access_token',
            'tiktok_pixel_code' => 'tiktok.pixel_code',
            'tiktok_access_token' => 'tiktok.access_token',
            'google_client_id' => 'google.client_id',
            'google_client_secret' => 'google.client_secret',
            'google_sheets_spreadsheet_id' => 'google_sheets.spreadsheet_id',
            'google_sheets_credentials_json' => 'google_sheets.credentials_json',
            'google_sheets_sync_token' => 'google_sheets.sync_token',
            'aliexpress_extension_token' => 'aliexpress_extension.token',
        ];

        foreach ($map as $field => $settingKey) {
            // Blank left as-is (masked placeholders mean "unchanged") —
            // only a non-empty submitted value overwrites a credential.
            if (filled($validated[$field] ?? null)) {
                AppSetting::set($settingKey, $validated[$field]);
                $changed[] = $settingKey;
            }
        }

        if (array_intersect($changed, ['google_sheets.credentials_json', 'google_sheets.spreadsheet_id'])) {
            Cache::forget('google_sheets_access_token');
        }

        if (array_intersect($changed, ['ga4.reporting_credentials_json', 'ga4.property_id'])) {
            Cache::forget('google_analytics_access_token');
        }

        if ($changed) {
            AuditLog::record('Gateway Settings Changed', 'Updated: '.implode(', ', $changed));
        }

        return back()->with('status', $changed ? 'Gateway settings updated.' : 'No changes submitted.');
    }

    public function backfillGoogleSheets(): RedirectResponse
    {
        set_time_limit(300);

        Artisan::call('sheets:backfill');
        $output = trim(Artisan::output());

        AuditLog::record('Google Sheets Backfill Run', $output);

        return back()->with('status', $output ?: 'Backfill finished.');
    }
}
