<?php

namespace App\Platform\Events;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * The at-least-once delivery sweep (foundations-domain-events-audit, task 4.2; design D6) — the
 * scheduled command that drains DUE `event_deliveries` rows through the same
 * {@see InlineDeliveryExecutor} path the inline post-commit hook uses. It is the durability
 * guarantee behind the inline fast path: it re-runs deliveries whose inline execution never
 * happened (a crash between the emitting commit and the hook) and retryable failures whose
 * exponential backoff has elapsed, dead-lettering a delivery (`failed`) once it reaches the
 * configured maximum attempts.
 *
 * Registered explicitly via withCommands() in bootstrap/app.php — auto-discovery only scans
 * app/Console/Commands, and design D1 keeps platform console commands beside their concern under
 * App\Platform\Events. Scheduled in routes/console.php at everyThirtySeconds()->withoutOverlapping(2):
 * a sub-minute cadence so a lost inline delivery surfaces within seconds, and withoutOverlapping(2) keeps
 * a slow tick from racing the next one (the double-execution guard) under a bounded 2-minute mutex lease
 * (TTL = expiresAt*60s) — a sweep that crashed without releasing the lock self-heals in ~2 min rather than
 * stalling every later tick for the framework's 24h default (C2, design D4). Sub-minute schedules need
 * `schedule:work` in the runtime — an ops note, not a test concern (tests invoke the command directly).
 */
class SweepCommand extends Command
{
    protected $signature = 'events:sweep';

    protected $description = 'Deliver due domain-event deliveries — the at-least-once delivery sweep.';

    public function handle(InlineDeliveryExecutor $executor): int
    {
        $result = $executor->deliverDue();
        $swept = $result['delivered'] + $result['failed'];

        // The run summary: how many deliveries this sweep ran (delivered + failed) and how many failed —
        // the dead-letter-in-place observability floor until an operator retry surface lands (C3, design D3).
        Log::info(sprintf('events:sweep complete: swept=%d failed=%d', $swept, $result['failed']), [
            'swept' => $swept,
            'failed' => $result['failed'],
        ]);

        return self::SUCCESS;
    }
}
