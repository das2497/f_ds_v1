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
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 me-3" data-bs-toggle="modal" data-bs-target="#editStaffModal{{ $member->id }}">Edit</button>
                            @if ($member->id !== auth()->id())
                                <form action="{{ route('admin.staff.destroy', $member) }}" method="POST" class="d-inline" onsubmit="return confirm('Revoke staff access for {{ $member->name }}?');">
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

    @foreach ($staff as $member)
        <div class="modal fade" id="editStaffModal{{ $member->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('admin.staff.update', $member) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h5 class="modal-title">Edit {{ $member->name }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small">Full Name</label>
                                <input type="text" name="name" class="form-control" value="{{ $member->name }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Email Address</label>
                                <input type="email" name="email" class="form-control" value="{{ $member->email }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Role</label>
                                <select name="role" class="form-select" {{ $member->id === auth()->id() ? 'disabled' : '' }}>
                                    <option value="admin" {{ $member->hasRole('admin') ? 'selected' : '' }}>Admin</option>
                                    <option value="super-admin" {{ $member->hasRole('super-admin') ? 'selected' : '' }}>Super Admin</option>
                                </select>
                                @if ($member->id === auth()->id())
                                    <input type="hidden" name="role" value="super-admin">
                                    <p class="small text-muted-slate mb-0 mt-1">You can't change your own role.</p>
                                @endif
                            </div>
                            <hr>
                            <div class="mb-3">
                                <label class="form-label small">New Password</label>
                                <input type="password" name="password" class="form-control" autocomplete="new-password">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Confirm New Password</label>
                                <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
                            </div>
                            <p class="small text-muted-slate mb-0">Leave the password fields blank to keep the current password.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-accent">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach

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
