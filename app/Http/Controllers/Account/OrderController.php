<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = $request->user()->orders()->withCount('items')->with('vendorOrders')->latest()->paginate(10);

        return view('account.orders', compact('orders'));
    }

    public function show(Request $request, Order $order): View
    {
        // Authorization: a customer can only ever see their OWN orders —
        // guessing another order's ID (even sequential, low ones) 403s.
        abort_unless($order->user_id === $request->user()->id, 403);

        $order->load('items', 'vendorOrders', 'refunds');

        return view('account.order-detail', compact('order'));
    }
}
