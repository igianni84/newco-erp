<?php

namespace App\Platform\Events;

use Illuminate\Console\Command;

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
 * App\Platform\Events. Scheduled in routes/console.php at everyThirtySeconds()->withoutOverlapping():
 * a sub-minute cadence so a lost inline delivery surfaces within seconds, and withoutOverlapping()
 * keeps a slow tick from racing the next one (the double-execution guard). Sub-minute schedules need
 * `schedule:work` in the runtime — an ops note, not a test concern (tests invoke the command directly).
 */
class SweepCommand extends Command
{
    protected $signature = 'events:sweep';

    protected $description = 'Deliver due domain-event deliveries — the at-least-once delivery sweep.';

    public function handle(InlineDeliveryExecutor $executor): int
    {
        $executor->deliverDue();

        return self::SUCCESS;
    }
}
