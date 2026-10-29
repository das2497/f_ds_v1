<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        $staff = User::role(['admin', 'super-admin'])->with('roles')->orderBy('name')->get();

        return view('admin.staff.index', compact('staff'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:admin,super-admin'],
        ]);

        $tempPassword = Str::password(16);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($tempPassword),
        ]);

        $user->assignRole($validated['role']);

        AuditLog::record('Staff Added', "{$user->name} ({$user->email}) added as {$validated['role']}");

        return back()->with('status', "{$user->name} added as {$validated['role']}. Temporary password: {$tempPassword} (share this securely — it won't be shown again).");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 422, "You can't remove your own staff access.");

        $roles = $user->getRoleNames()->implode(', ');
        $user->syncRoles(['customer']);

        AuditLog::record('Staff Removed', "{$user->name} ({$user->email}) removed from: {$roles}");

        return back()->with('status', "{$user->name}'s staff access was revoked.");
    }
}
