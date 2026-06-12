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
// withoutOverlapping() keeps a slow sweep from racing the next tick (the double-execution guard). The
// command is App\Platform\Events\SweepCommand, registered via withCommands() in bootstrap/app.php.
Schedule::command('events:sweep')->everyThirtySeconds()->withoutOverlapping();
