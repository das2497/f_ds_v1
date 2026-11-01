<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = User::role('customer')
            ->withCount('orders')
            ->withSum(['orders as lifetime_spend' => fn ($query) => $query->where('status', 'paid')], 'total')
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($query2) => $query2
                ->where('name', 'like', '%'.$request->string('q').'%')
                ->orWhere('email', 'like', '%'.$request->string('q').'%')
            ))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    public function show(User $customer): View
    {
        abort_unless($customer->hasRole('customer'), 404);

        $customer->load(['orders' => fn ($query) => $query->latest(), 'wishlists.product']);

        $lifetimeSpend = $customer->orders->where('status', 'paid')->sum('total');

        return view('admin.customers.show', compact('customer', 'lifetimeSpend'));
    }
}
