<?php

use App\Platform\Audit\AuditRecord;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DeliveryStatus;
use App\Platform\Events\Demo\DemoCommand;
use App\Platform\Events\DomainEvent;
use App\Platform\Events\EventDelivery;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Pins the operator-runnable `events:demo` command (foundations-domain-events-audit, task 5.1;
 * design D9) — the Hello-World Demonstration's "demo command runs the full trail" scenario. It
 * asserts the command exits 0 and its output shows the recorded event (with `event_id` and
 * `actor_role`), the audit record, the delivery completing, and both immutability probes being
 * rejected; and that the command is re-runnable (the failure/edge case — a second run also exits 0).
 *
 * Output is asserted via Artisan::call() (a clean `int` exit code) + Artisan::output() (the buffered
 * trail as a `string`), NOT the Pest artisan() helper: that helper is PHPDoc'd `@return
 * PendingCommand|int`, so chaining ->expectsOutputToContain() trips PHPStan max on the `int` arm.
 *
 * Trait choice — DatabaseMigrations, NOT RefreshDatabase: the command's DB::transaction() must really
 * COMMIT at transactionLevel 0 so its post-commit inline-delivery hook fires (the trail's `done`
 * line), which only happens outside RefreshDatabase's wrapper transaction.
 */
uses(DatabaseMigrations::class);

it('runs the full trail and exits 0', function () {
    expect(Artisan::call('events:demo'))->toBe(0);

    $output = Artisan::output();

    // The four things the scenario requires the trail to show.
    expect($output)
        ->toContain('Recorded domain event: '.DemoCommand::EVENT_NAME)   // the event…
        ->toContain('event_id:')                                          // …with its event_id…
        ->toContain('actor_role: '.ActorRole::System->value)              // …and its actor_role,
        ->toContain('Recorded audit record: '.DemoCommand::AUDIT_ACTION)  // the audit record,
        ->toContain('done (attempts 1)')                                  // the delivery completing,
        ->toContain('UPDATE domain_events: rejected')                     // and both immutability probes
        ->toContain('DELETE domain_events: rejected');                    // being rejected.

    // The trail reflects real persisted state: one event whose delivery is `done`, an audit row, and
    // the event row left intact by the probes.
    $event = DomainEvent::query()->where('name', DemoCommand::EVENT_NAME)->sole();

    expect($event->actor_role)->toBe(ActorRole::System)
        ->and($event->name)->toBe(DemoCommand::EVENT_NAME)
        ->and(EventDelivery::query()->where('domain_event_id', $event->id)->sole()->status)->toBe(DeliveryStatus::Done)
        ->and(AuditRecord::query()->where('action', DemoCommand::AUDIT_ACTION)->exists())->toBeTrue();
});

it('is re-runnable: a second run also exits 0, appending another event while the state row stays single', function () {
    expect(Artisan::call('events:demo'))->toBe(0);
    expect(Artisan::call('events:demo'))->toBe(0);

    // domain_events / audit_records are append-only → two of each (each event carries a fresh UUID, so
    // no unique collision); the cache state write is idempotent → exactly one row.
    expect(DomainEvent::query()->where('name', DemoCommand::EVENT_NAME)->count())->toBe(2)
        ->and(AuditRecord::query()->where('action', DemoCommand::AUDIT_ACTION)->count())->toBe(2)
        ->and(DB::table('cache')->where('key', DemoCommand::STATE_KEY)->count())->toBe(1)
        // both runs' deliveries completed (no pending/failed left behind).
        ->and(EventDelivery::query()->where('status', DeliveryStatus::Done->value)->count())->toBe(2);
});
