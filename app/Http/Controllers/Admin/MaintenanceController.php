<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public function index(): View
    {
        $backups = collect(glob(storage_path('app/backups/*.sql')))
            ->map(fn ($path) => [
                'name' => basename($path),
                'size' => filesize($path),
                'created_at' => Carbon::createFromTimestamp(filemtime($path)),
            ])
            ->sortByDesc('created_at')
            ->values();

        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->latest('failed_at')->limit(20)->get();

        return view('admin.maintenance.index', [
            'backups' => $backups,
            'pendingJobs' => $pendingJobs,
            'failedJobCount' => DB::table('failed_jobs')->count(),
            'failedJobs' => $failedJobs,
        ]);
    }

    public function backup(): RedirectResponse
    {
        Artisan::call('app:backup-database');

        AuditLog::record('Manual Database Backup', trim(Artisan::output()));

        return back()->with('status', 'Backup complete — see the list below.');
    }

    public function downloadBackup(string $filename)
    {
        $path = storage_path("app/backups/{$filename}");

        abort_unless(
            str_ends_with($filename, '.sql') && ! str_contains($filename, '..') && file_exists($path),
            404
        );

        return response()->download($path);
    }

    public function retryFailedJob(Request $request, string $uuid): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);

        return back()->with('status', 'Job re-queued.');
    }
}
