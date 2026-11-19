<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards a machine-to-machine webhook (the Sheet's Apps Script, the
 * AliExpress extension) with a single shared Bearer token instead of
 * session/Sanctum auth, since each caller is a single trusted script the
 * admin controls, not a multi-user API client. The token is whatever the
 * admin set on the Super Admin > Gateway Settings page, falling back to
 * the matching .env value for a server provisioned before that panel
 * existed.
 */
class VerifyWebhookToken
{
    /**
     * @var array<string, array{setting: string, config: string}>
     */
    private const TOKENS = [
        'sheet-sync' => ['setting' => 'google_sheets.sync_token', 'config' => 'services.google_sheets.sync_token'],
        'aliexpress' => ['setting' => 'aliexpress_extension.token', 'config' => 'services.aliexpress_extension.token'],
    ];

    public function handle(Request $request, Closure $next, string $name): Response
    {
        $tokenMap = self::TOKENS[$name] ?? null;

        abort_if($tokenMap === null, 500, "Unknown webhook token \"{$name}\".");

        $expected = AppSetting::get($tokenMap['setting']) ?: config($tokenMap['config']);

        if (blank($expected) || ! hash_equals($expected, (string) $request->bearerToken())) {
            abort(401, 'Invalid or missing webhook token.');
        }

        return $next($request);
    }
}
