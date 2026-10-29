<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::whereHas('wishlistedBy', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with('variants')
            ->get();

        return view('store.wishlist', compact('products'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
        ]);

        $request->user()->wishlists()->firstOrCreate(['product_id' => $validated['product_id']]);

        return back()->with('status', 'Added to wishlist.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $request->user()->wishlists()->where('product_id', $product->id)->delete();

        return back()->with('status', 'Removed from wishlist.');
    }
}
