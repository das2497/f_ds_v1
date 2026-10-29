<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::with('user')
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q2) => $q2
                ->where('action', 'like', '%'.$request->string('q').'%')
                ->orWhere('details', 'like', '%'.$request->string('q').'%')
            ))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $admins = User::role(['admin', 'super-admin'])->orderBy('name')->get(['id', 'name']);

        return view('admin.audit-logs.index', compact('logs', 'admins'));
    }
}
