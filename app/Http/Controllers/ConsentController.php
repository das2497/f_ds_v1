<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Minimal cookie-consent gate (1_0.txt §7 — "Consent management... එකතු
 * කරන්න"). Only gates what's optional: Meta/TikTok click-id cookies and the
 * browser marketing pixels. First-party UTM attribution and order records
 * are never gated behind this — they're needed to fulfil the order itself.
 */
class ConsentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['choice' => ['required', 'in:granted,denied']]);

        Cookie::queue('analytics_consent', $validated['choice'], 60 * 24 * 365);

        return back();
    }
}
