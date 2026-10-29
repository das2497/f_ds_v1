<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $orders = $user->orders()->withCount('items')->with('vendorOrders')->latest()->limit(3)->get();

        return view('account.dashboard', [
            'orders' => $orders,
            'totalOrders' => $user->orders()->count(),
            'activeOrders' => $user->orders()->whereIn('status', ['pending_payment', 'paid'])->count(),
        ]);
    }
}
