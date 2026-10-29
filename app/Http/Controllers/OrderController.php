<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function success(Request $request, Order $order): View
    {
        abort_unless($order->isViewableBy($request), 403);

        $order->load(['items', 'refunds']);

        return view('store.order-success', compact('order'));
    }
}
