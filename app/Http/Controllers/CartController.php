<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Services\Analytics\EventTracker;
use App\Services\Cart\CartResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        private readonly CartResolver $cartResolver,
        private readonly EventTracker $tracker,
    ) {}

    public function index(Request $request): View
    {
        $cart = $this->cartResolver->resolve($request);
        $cart->load('items.variant.product');

        $subtotal = round($cart->items->sum(fn ($item) => $item->lineTotal()), 2);

        return view('store.cart', compact('cart', 'subtotal'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $variant = ProductVariant::findOrFail($validated['product_variant_id']);
        abort_if($variant->stock_status === 'out_of_stock' || ! $variant->is_active, 422, 'This item is out of stock.');

        $cart = $this->cartResolver->resolve($request);
        $quantity = $validated['quantity'] ?? 1;

        $item = $cart->items()->where('product_variant_id', $variant->id)->first();

        if ($item) {
            $item->increment('quantity', $quantity);
        } else {
            $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => $quantity]);
        }

        $this->tracker->track($request, 'add_to_cart', [
            'product_id' => $variant->product_id,
            'value' => $variant->price,
            'currency' => 'USD',
        ]);

        return back()->with('status', 'Added to cart.');
    }

    public function update(Request $request, CartItem $cartItem): RedirectResponse|JsonResponse
    {
        $this->authorizeCartItem($request, $cartItem);

        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:20']]);

        $cartItem->update(['quantity' => $validated['quantity']]);

        if ($request->wantsJson()) {
            return response()->json(array_merge([
                'item' => [
                    'id' => $cartItem->id,
                    'quantity' => $cartItem->quantity,
                    'line_total' => number_format($cartItem->lineTotal(), 2),
                    'unit_price' => number_format($cartItem->unitPrice(), 2),
                ],
            ], $this->cartSummary($this->cartResolver->resolve($request))));
        }

        return back();
    }

    public function destroy(Request $request, CartItem $cartItem): RedirectResponse|JsonResponse
    {
        $this->authorizeCartItem($request, $cartItem);

        $cartItem->delete();

        if ($request->wantsJson()) {
            return response()->json($this->cartSummary($this->cartResolver->resolve($request)));
        }

        return back()->with('status', 'Item removed.');
    }

    private function authorizeCartItem(Request $request, CartItem $cartItem): void
    {
        $cart = $this->cartResolver->resolve($request);

        abort_unless($cartItem->cart_id === $cart->id, 403);
    }

    /**
     * @return array{subtotal: string, cart_count: int, has_unavailable_items: bool}
     */
    private function cartSummary(Cart $cart): array
    {
        $items = $cart->items()->with('variant.product')->get();

        return [
            'subtotal' => number_format(round($items->sum(fn ($item) => $item->lineTotal()), 2), 2),
            'cart_count' => (int) $items->sum('quantity'),
            'has_unavailable_items' => $items->contains(
                fn ($item) => $item->variant->stock_status === 'out_of_stock' || ! $item->variant->is_active
            ),
        ];
    }
}
