<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The at-least-once delivery sweep (foundations-domain-events-audit, task 4.2; design D6): a
// sub-minute tick drains due event_deliveries the inline post-commit hook never ran (a crash between
// the emitting commit and the hook) and retryable failures whose backoff has elapsed.
// withoutOverlapping(2) keeps a slow sweep from racing the next tick (the double-execution guard) under a
// bounded 2-minute mutex lease (TTL = expiresAt*60s) so a sweep that crashed mid-run without releasing the
// lock self-heals in ~2 min rather than stalling every later tick for the framework's 24h default (C2,
// design D4). The command is App\Platform\Events\SweepCommand, registered via withCommands() in bootstrap/app.php.
Schedule::command('events:sweep')->everyThirtySeconds()->withoutOverlapping(2);

// The daily enhanced-KYC AML-threshold scan (parties-enhanced-kyc-threshold, task 5.1; design D7): iterates every
// Customer and escalates a first €10k-single / €50k-rolling-12mo breach through EvaluateEnhancedKycThreshold. Runs
// INLINE on the scheduler tick (design D7 — a scheduler tick is not a queued workflow, so the open queue-driver ADR
// gate stays untouched); with the launch null totals reader it is a no-op until Module S lands the real adapter. The
// command is App\Modules\Parties\Console\ScanEnhancedKycThresholds, registered via withCommands() in bootstrap/app.php.
Schedule::command('parties:scan-enhanced-kyc-thresholds')->daily();
