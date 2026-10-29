<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TaxRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaxRateController extends Controller
{
    public function index(): View
    {
        $taxRates = TaxRate::orderBy('state_name')->get();

        return view('admin.tax-rates.index', compact('taxRates'));
    }

    public function update(Request $request, TaxRate $taxRate): RedirectResponse
    {
        $validated = $request->validate([
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:20'],
        ]);

        $oldRate = (float) $taxRate->rate;
        $newRate = round($validated['rate_percent'] / 100, 4);

        $taxRate->update(['rate' => $newRate]);

        AuditLog::record(
            'Tax Rate Updated',
            "{$taxRate->state_name} ({$taxRate->state_code}): ".number_format($oldRate * 100, 2).'% → '.number_format($newRate * 100, 2).'%'
        );

        return back()->with('status', "{$taxRate->state_name} tax rate updated.");
    }
}
