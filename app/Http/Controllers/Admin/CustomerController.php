<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = $this->customerQuery()
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
        $this->authorizeCustomer($customer);

        $customer->load(['orders' => fn ($query) => $query->latest(), 'wishlists.product']);

        $lifetimeSpend = $customer->orders->where('status', 'paid')->sum('total');

        return view('admin.customers.show', compact('customer', 'lifetimeSpend'));
    }

    public function suspend(User $customer): RedirectResponse
    {
        $this->authorizeCustomer($customer);

        $customer->update(['suspended_at' => now()]);

        AuditLog::record('Customer Suspended', "{$customer->name} ({$customer->email}) suspended");

        return back()->with('status', "{$customer->name}'s account was suspended.");
    }

    public function reactivate(User $customer): RedirectResponse
    {
        $this->authorizeCustomer($customer);

        $customer->update(['suspended_at' => null]);

        AuditLog::record('Customer Reactivated', "{$customer->name} ({$customer->email}) reactivated");

        return back()->with('status', "{$customer->name}'s account was reactivated.");
    }

    public function destroy(User $customer): RedirectResponse
    {
        $this->authorizeCustomer($customer);

        $name = $customer->name;
        $email = $customer->email;

        $customer->delete();

        AuditLog::record('Customer Deleted', "{$name} ({$email}) deleted");

        return redirect()->route('admin.customers')->with('status', "{$name}'s account was deleted.");
    }

    /**
     * @return Builder<User>
     */
    private function customerQuery(): Builder
    {
        return User::role('customer')
            ->whereDoesntHave('roles', fn ($query) => $query->whereIn('name', ['admin', 'super-admin']));
    }

    private function authorizeCustomer(User $customer): void
    {
        abort_unless($customer->hasRole('customer') && ! $customer->hasAnyRole(['admin', 'super-admin']), 404);
    }
}
