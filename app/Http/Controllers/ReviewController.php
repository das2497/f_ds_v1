<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();

        abort_unless($product->isPurchasedBy($user), 403, 'Only customers who purchased this product can leave a review.');

        if ($product->reviews()->where('user_id', $user->id)->exists()) {
            return back()->with('status', 'You have already reviewed this product.');
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $product->reviews()->create([
            'user_id' => $user->id,
            'rating' => $validated['rating'],
            'body' => $validated['body'],
        ]);

        return back()->with('status', 'Thanks for your review!');
    }
}
