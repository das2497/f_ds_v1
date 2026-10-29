<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\VendorOrder;
use App\Services\Fulfillment\FulfillmentEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::query()
            ->with('vendorOrders')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q2) => $q2
                ->where('order_number', 'like', '%'.$request->string('q').'%')
                ->orWhere('shipping_name', 'like', '%'.$request->string('q').'%')
            ))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders.index', compact('orders'));
    }

    public function show(Order $order): View
    {
        $order->load(['items', 'vendorOrders.items.orderItem', 'vendorOrders.attempts', 'payment', 'refunds', 'user']);

        return view('admin.orders.show', compact('order'));
    }

    public function retryVendorOrder(VendorOrder $vendorOrder, FulfillmentEngine $engine): RedirectResponse
    {
        $vendorOrder->update(['status' => 'pending']);
        $engine->submitVendorOrder($vendorOrder);
        $engine->notifyIfFullySubmitted($vendorOrder->order);

        return back()->with('status', 'Retry attempted — check the fulfillment log below.');
    }

    public function retryQueue(): View
    {
        $failed = VendorOrder::with(['order', 'attempts'])
            ->withCount('attempts')
            ->where('status', 'failed')
            ->latest()
            ->get();

        return view('admin.orders.retry', compact('failed'));
    }
}
