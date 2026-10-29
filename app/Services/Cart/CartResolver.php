<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Resolves the current visitor's cart — a persistent cookie token for guests
 * (not Laravel's session id, which rotates on login) merged into the user's
 * cart once they sign in.
 */
class CartResolver
{
    private const COOKIE = 'cart_token';

    public function resolve(Request $request): Cart
    {
        if ($request->user()) {
            return Cart::firstOrCreate(['user_id' => $request->user()->id]);
        }

        $token = $request->cookie(self::COOKIE) ?? (string) Str::uuid();
        Cookie::queue(self::COOKIE, $token, 60 * 24 * 365);

        return Cart::firstOrCreate(['session_id' => $token]);
    }

    /**
     * Item count for the badge — reads without creating a cart row for
     * visitors who have never added anything yet.
     */
    public function currentCount(Request $request): int
    {
        $cart = $request->user()
            ? Cart::where('user_id', $request->user()->id)->first()
            : Cart::where('session_id', $request->cookie(self::COOKIE))->first();

        return $cart ? (int) $cart->items()->sum('quantity') : 0;
    }

    public function mergeGuestCartIntoUser(Request $request, User $user): void
    {
        $token = $request->cookie(self::COOKIE);

        if (! $token) {
            return;
        }

        $guestCart = Cart::where('session_id', $token)->first();

        if (! $guestCart) {
            return;
        }

        $userCart = Cart::firstOrCreate(['user_id' => $user->id]);

        foreach ($guestCart->items as $guestItem) {
            $existing = $userCart->items()->where('product_variant_id', $guestItem->product_variant_id)->first();

            if ($existing) {
                $existing->increment('quantity', $guestItem->quantity);
            } else {
                $guestItem->update(['cart_id' => $userCart->id]);
            }
        }

        $guestCart->delete();
        Cookie::queue(Cookie::forget(self::COOKIE));
    }
}
