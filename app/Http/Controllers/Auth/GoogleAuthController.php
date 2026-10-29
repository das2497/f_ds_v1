<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\Cart\CartResolver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * Credentials are read from admin/settings-gateways (stored encrypted in
 * app_settings) first, falling back to .env — same pattern as CJService.
 * Socialite reads config('services.google') fresh on every driver() call, so
 * applying the override just means setting that config before each call.
 */
class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (! $this->configured()) {
            return redirect()->route('login')->with('error', 'Google sign-in is not configured yet.');
        }

        $this->applyCredentials();

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, CartResolver $cartResolver): RedirectResponse
    {
        if (! $this->configured()) {
            return redirect()->route('login')->with('error', 'Google sign-in is not configured yet.');
        }

        $this->applyCredentials();

        $googleUser = Socialite::driver('google')->user();

        $user = User::where('google_id', $googleUser->getId())->first();

        if (! $user) {
            $user = User::where('email', $googleUser->getEmail())->first();
        }

        if ($user) {
            $user->update([
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
            ]);
        } else {
            $user = User::create([
                'name' => $googleUser->getName() ?? $googleUser->getNickname(),
                'email' => $googleUser->getEmail(),
                'password' => Hash::make(Str::random(32)),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'email_verified_at' => now(),
            ]);

            $user->assignRole('customer');

            event(new Registered($user));
        }

        Auth::login($user, remember: true);

        $cartResolver->mergeGuestCartIntoUser($request, $user);

        return redirect(route('account.dashboard', absolute: false));
    }

    private function configured(): bool
    {
        return filled($this->clientId()) && filled($this->clientSecret());
    }

    private function clientId(): ?string
    {
        return AppSetting::get('google.client_id') ?: config('services.google.client_id');
    }

    private function clientSecret(): ?string
    {
        return AppSetting::get('google.client_secret') ?: config('services.google.client_secret');
    }

    private function applyCredentials(): void
    {
        config([
            'services.google.client_id' => $this->clientId(),
            'services.google.client_secret' => $this->clientSecret(),
        ]);
    }
}
