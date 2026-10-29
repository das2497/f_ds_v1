<x-admin-layout title="Audit Logs">
    <form action="{{ route('admin.audit-logs') }}" method="GET" class="d-flex gap-2 mb-3 flex-wrap">
        <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search logs..." style="width:220px">
        <select name="user_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="">All Admins</option>
            @foreach ($admins as $admin)
                <option value="{{ $admin->id }}" @selected((string) request('user_id') === (string) $admin->id)>{{ $admin->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
        @if (request('q') || request('user_id'))
            <a href="{{ route('admin.audit-logs') }}" class="btn btn-sm btn-link text-muted-slate text-decoration-none">Clear</a>
        @endif
    </form>

    <div class="table-admin p-3">
        <table class="table table-admin align-middle mb-0">
            <thead>
                <tr><th>Timestamp</th><th>Admin</th><th>Action</th><th>Details</th></tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="small">{{ $log->created_at->format('M j, Y g:i A') }}</td>
                        <td>{{ $log->user?->name ?? 'System' }}</td>
                        <td>{{ $log->action }}</td>
                        <td class="small text-muted-slate">{{ $log->details }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted-slate py-4">No audit log entries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3 mb-3">{{ $logs->links() }}</div>

    <div class="small text-muted-slate mt-3"><i class="bi bi-lock me-1"></i>This log is immutable — entries cannot be edited or deleted.</div>
</x-admin-layout>
