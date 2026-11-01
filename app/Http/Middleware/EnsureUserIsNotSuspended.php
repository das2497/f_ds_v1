<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Catches an already-logged-in customer whose account is suspended mid-session
 * (login-time checks in LoginRequest/GoogleAuthController only cover new logins).
 */
class EnsureUserIsNotSuspended
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->isSuspended()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'This account has been suspended. Contact support for help.');
        }

        return $next($request);
    }
}
