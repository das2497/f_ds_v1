<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// cPanel cron runs `schedule:run` every minute (1_0.txt §9); this itself
// only runs every 5 minutes and skips overlapping runs so a slow vendor
// API call can't pile up concurrent executions.
Schedule::command('app:vendor-sync-job')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Checkouts customers never finish paying for sit at pending_payment
// forever otherwise — this just closes them out after a day's grace period.
Schedule::command('app:expire-abandoned-orders')
    ->hourly()
    ->withoutOverlapping();

// Shared hosting has no persistent queue worker, so the scheduler runs one
// bounded batch a minute instead (1_0.txt §9 — "cron විසින් කුඩා, සීමිත
// කාලයක් ක්‍රියාත්මක වන worker batches process කරන්න"). --stop-when-empty
// exits as soon as the queue drains; --max-time is a hard ceiling so a
// stuck job can't run into the next minute's overlapping invocation.
// If a job (or the whole process) is killed mid-run, the database queue's
// retry_after (config/queue.php) makes it reservable again automatically
// — nothing is lost, the next scheduled batch just picks it back up.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();
