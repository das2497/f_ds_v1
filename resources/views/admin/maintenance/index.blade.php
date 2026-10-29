<x-admin-layout title="Maintenance">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="table-admin p-4">
                <h6 class="mb-3"><i class="bi bi-database me-2 text-accent"></i>Database Backups</h6>
                <p class="small text-muted-slate">Trigger a manual database backup. The dump is stored on this server's disk.</p>
                <form action="{{ route('admin.maintenance.backup') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-accent btn-sm mb-3"><i class="bi bi-download"></i> Create Backup Now</button>
                </form>
                <table class="table table-admin align-middle mb-0">
                    <thead><tr><th>File</th><th>Size</th><th>Created</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($backups as $backup)
                            <tr>
                                <td class="small">{{ $backup['name'] }}</td>
                                <td class="small">{{ number_format($backup['size'] / 1048576, 1) }} MB</td>
                                <td class="small">{{ $backup['created_at']->format('M j, Y g:i A') }}</td>
                                <td><a href="{{ route('admin.maintenance.backups.download', $backup['name']) }}" class="small text-accent text-decoration-none">Download</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted-slate py-4">No backups yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="table-admin p-4 mb-3">
                <h6 class="mb-3"><i class="bi bi-hourglass-split me-2 text-accent"></i>Queue &amp; Jobs</h6>
                <div class="row-line"><span class="text-muted-slate">Pending Jobs</span><span class="fw-semibold">{{ $pendingJobs }}</span></div>
                <div class="row-line mb-0"><span class="text-muted-slate">Failed Jobs</span><span class="fw-semibold {{ $failedJobCount > 0 ? 'text-danger' : '' }}">{{ $failedJobCount }}</span></div>
            </div>

            @if ($failedJobs->isNotEmpty())
                <div class="table-admin p-4">
                    <h6 class="mb-3"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Failed Jobs</h6>
                    <table class="table table-admin align-middle mb-0">
                        <thead><tr><th>Queue</th><th>Failed At</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($failedJobs as $job)
                                <tr>
                                    <td class="small">{{ $job->queue }}</td>
                                    <td class="small">{{ \Illuminate\Support\Carbon::parse($job->failed_at)->format('M j, Y g:i A') }}</td>
                                    <td>
                                        <form action="{{ route('admin.maintenance.failed-jobs.retry', $job->uuid) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-link text-accent text-decoration-none p-0">Retry</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
