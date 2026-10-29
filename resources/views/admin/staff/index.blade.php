<x-admin-layout title="Staff Management">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-accent btn-sm" data-bs-toggle="modal" data-bs-target="#addStaffModal"><i class="bi bi-plus-lg"></i> Add Staff Member</button>
    </div>

    <div class="table-admin p-3">
        <table class="table table-admin align-middle mb-0">
            <thead>
                <tr><th>Name</th><th>Email</th><th>Role</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($staff as $member)
                    <tr>
                        <td>{{ $member->name }}</td>
                        <td>{{ $member->email }}</td>
                        <td>
                            @if ($member->hasRole('super-admin'))
                                <span class="badge rounded-pill" style="background:#FEE2E2;color:#991B1B">Super Admin</span>
                            @else
                                <span class="badge rounded-pill" style="background:#DBEAFE;color:#1E40AF">Admin</span>
                            @endif
                        </td>
                        <td>
                            @if ($member->id !== auth()->id())
                                <form action="{{ route('admin.staff.destroy', $member) }}" method="POST" onsubmit="return confirm('Revoke staff access for {{ $member->name }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-link text-danger text-decoration-none p-0">Remove</button>
                                </form>
                            @else
                                <span class="small text-muted-slate">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted-slate py-4">No staff members yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="modal fade" id="addStaffModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('admin.staff.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Staff Member</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small">Full Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Email Address</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Role</label>
                            <select name="role" class="form-select">
                                <option value="admin">Admin</option>
                                <option value="super-admin">Super Admin</option>
                            </select>
                        </div>
                        <p class="small text-muted-slate mb-0">A random temporary password will be generated and shown once after creation.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-accent">Add Staff Member</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
