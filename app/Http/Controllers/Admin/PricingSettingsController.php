<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PricingSettingsController extends Controller
{
    private const SETTING_KEY = 'pricing.profit_margin_percent';

    public const DEFAULT_PROFIT_MARGIN_PERCENT = 150.0;

    /**
     * The markup super-admin has configured over vendor/own cost — e.g. 150
     * means a retail price suggestion of cost * 2.5. Shared by every place
     * that suggests a selling price when a product or variant is added, so
     * changing it here takes effect everywhere at once.
     */
    public static function profitMarginPercent(): float
    {
        $value = AppSetting::get(self::SETTING_KEY);

        return $value !== null ? (float) $value : self::DEFAULT_PROFIT_MARGIN_PERCENT;
    }

    public function index(): View
    {
        return view('admin.pricing-settings.index', [
            'profitMarginPercent' => self::profitMarginPercent(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'profit_margin_percent' => ['required', 'numeric', 'min:0', 'max:1000'],
        ]);

        $old = self::profitMarginPercent();
        $new = (float) $validated['profit_margin_percent'];

        AppSetting::set(self::SETTING_KEY, $new);

        AuditLog::record(
            'Profit Margin Updated',
            number_format($old, 2).'% → '.number_format($new, 2).'%'
        );

        return back()->with('status', 'Profit margin updated.');
    }
}
