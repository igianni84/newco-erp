<?php

use App\Platform\Events\ActorRole;
use App\Platform\Events\DeliveryStatus;
use App\Platform\Events\DomainEvent;
use App\Platform\Events\EventDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Platform\FailingConsumer;
use Tests\Support\Platform\RecordingConsumer;

/**
 * Pins the `events:sweep` command, its schedule entry and the executor's deliverDue() path
 * (foundations-domain-events-audit, task 4.2; design D6). Covers the delta-spec Inline Delivery and
 * Scheduled Sweep scenarios the inline path (task 4.1) does NOT: crash recovery (the sweep delivers
 * what inline never ran), exponential backoff then dead-letter, and the poison-no-stall property
 * (no per-consumer FIFO).
 *
 * Trait choice — DatabaseMigrations, NOT RefreshDatabase (same reasoning as the recorder/inline
 * tests): the executor's per-delivery transaction must really COMMIT/ROLL BACK at transactionLevel
 * 0, which only happens outside RefreshDatabase's wrapper transaction; and the sweep is invoked
 * through Artisan::call(), which runs the command against the same live connection.
 */
uses(DatabaseMigrations::class);

// The consumer sink is PHP-process state (the trait resets the DB, not statics) — clear it before
// each test so invocation-count/order assertions never see a sibling test's deliveries.
beforeEach(fn () => RecordingConsumer::$handled = []);

// Carbon's global test-now is process state too; reset it so a frozen clock never leaks across tests.
afterEach(fn () => CarbonImmutable::setTestNow());

/**
 * Seeds a committed domain event + one delivery row in a chosen state, WITHOUT the recorder (so no
 * afterCommit hook fires) — exactly the ledger state a crash between commit and inline execution
 * leaves behind, and the sweep's input. Overrides let each test plant the precise row state its
 * scenario needs (a prior attempt count, a future backoff window). Prefixed to avoid colliding with
 * sibling test files' global Pest helpers (one shared namespace).
 *
 * @param  array<string, mixed>  $deliveryOverrides
 */
function sweepSeedDelivery(string $consumerClass, array $deliveryOverrides = [], string $entityId = '1'): EventDelivery
{
    return DB::transaction(function () use ($consumerClass, $deliveryOverrides, $entityId): EventDelivery {
        $event = DomainEvent::create([
            'event_id' => (string) Str::uuid7(),
            'name' => 'SweepProbe',
            'schema_version' => 1,
            'module' => 'platform',
            'occurred_at' => CarbonImmutable::now('UTC'),
            'actor_role' => ActorRole::System,
            'actor_id' => null,
            'entity_type' => 'sweep-demo',
            'entity_id' => $entityId,
            'correlation_id' => (string) Str::uuid7(),
            'causation_id' => null,
            'payload' => [],
        ]);

        return EventDelivery::create(array_merge([
            'domain_event_id' => $event->id,
            'consumer' => $consumerClass,
            'status' => DeliveryStatus::Pending,
            'attempts' => 0,
        ], $deliveryOverrides));
    });
}

it('delivers a committed pending delivery that the inline hook never ran (crash recovery)', function () {
    $delivery = sweepSeedDelivery(RecordingConsumer::class);

    expect($delivery->status)->toBe(DeliveryStatus::Pending);   // seeded without the recorder hook → never delivered

    expect(Artisan::call('events:sweep'))->toBe(0);

    $delivery->refresh();
    expect($delivery->status)->toBe(DeliveryStatus::Done)        // the sweep delivered it — no event lost
        ->and($delivery->attempts)->toEqual(1)
        ->and(RecordingConsumer::$handled)->toBe([$delivery->domain_event_id]);
});

it('applies exponential backoff and skips a row whose backoff has not yet elapsed', function () {
    $t0 = CarbonImmutable::parse('2026-06-12 12:00:00', 'UTC');
    CarbonImmutable::setTestNow($t0);

    $delivery = sweepSeedDelivery(FailingConsumer::class);

    // First sweep at T0: the handler fails → attempt 1, still `pending` (1 < max), backoff = base (30s).
    expect(Artisan::call('events:sweep'))->toBe(0);
    $delivery->refresh();
    expect($delivery->attempts)->toEqual(1)
        ->and($delivery->status)->toBe(DeliveryStatus::Pending)
        ->and($delivery->last_error)->toContain(FailingConsumer::FAILURE_MESSAGE)
        ->and($delivery->available_at?->equalTo($t0->addSeconds(30)))->toBeTrue();   // window = base

    // Still at T0: the backoff window has not elapsed, so the sweep skips the row (attempts unchanged).
    expect(Artisan::call('events:sweep'))->toBe(0);
    $delivery->refresh();
    expect($delivery->attempts)->toEqual(1)
        ->and($delivery->available_at?->equalTo($t0->addSeconds(30)))->toBeTrue();

    // Past the first window: a second failure → attempt 2 with a LATER, LARGER window (60s > 30s).
    CarbonImmutable::setTestNow($t0->addSeconds(31));
    expect(Artisan::call('events:sweep'))->toBe(0);
    $delivery->refresh();
    expect($delivery->attempts)->toEqual(2)
        ->and($delivery->status)->toBe(DeliveryStatus::Pending)
        ->and($delivery->available_at?->equalTo($t0->addSeconds(31)->addSeconds(60)))->toBeTrue()
        ->and($delivery->available_at?->greaterThan($t0->addSeconds(30)))->toBeTrue();   // window grew (exponential)
});

it('dead-letters a delivery at max attempts and never executes it again', function () {
    $t0 = CarbonImmutable::parse('2026-06-12 12:00:00', 'UTC');
    $maxAttempts = Config::integer('events.sweep.max_attempts');

    $delivery = sweepSeedDelivery(FailingConsumer::class);

    // One failing sweep per attempt, each advanced past the previous (capped) backoff window so the
    // row is due again every time.
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        CarbonImmutable::setTestNow($t0->addHours($attempt));   // > the 3600s cap → always due
        expect(Artisan::call('events:sweep'))->toBe(0);
    }

    $delivery->refresh();
    expect($delivery->status)->toBe(DeliveryStatus::Failed)      // dead-lettered in place
        ->and($delivery->attempts)->toEqual($maxAttempts);

    // A further sweep never executes a `failed` row (the due query filters `pending`), so the attempt
    // count stays put — the dead-letter is terminal until an operator retry surface (a later change).
    CarbonImmutable::setTestNow($t0->addHours($maxAttempts + 1));
    expect(Artisan::call('events:sweep'))->toBe(0);
    $delivery->refresh();
    expect($delivery->status)->toBe(DeliveryStatus::Failed)
        ->and($delivery->attempts)->toEqual($maxAttempts);       // unchanged → not re-executed
});

it('does not let a poison delivery in backoff stall a due delivery for the same consumer', function () {
    $t0 = CarbonImmutable::parse('2026-06-12 12:00:00', 'UTC');
    CarbonImmutable::setTestNow($t0);

    // An earlier event's delivery for the consumer is stuck in backoff (a prior failure, not yet due).
    $poison = sweepSeedDelivery(RecordingConsumer::class, [
        'attempts' => 1,
        'available_at' => $t0->addMinutes(5),
        'last_error' => 'earlier failure',
    ], entityId: 'poison');

    // A later event's delivery for the SAME consumer is due now.
    $fresh = sweepSeedDelivery(RecordingConsumer::class, entityId: 'fresh');

    expect(Artisan::call('events:sweep'))->toBe(0);

    $poison->refresh();
    $fresh->refresh();

    expect($fresh->status)->toBe(DeliveryStatus::Done)               // the due delivery completed…
        ->and($fresh->attempts)->toEqual(1)
        ->and($poison->status)->toBe(DeliveryStatus::Pending)        // …while the poison row was skipped…
        ->and($poison->attempts)->toEqual(1)                         // …not re-attempted…
        ->and($poison->available_at?->equalTo($t0->addMinutes(5)))->toBeTrue()   // …its backoff untouched…
        ->and(RecordingConsumer::$handled)->toBe([$fresh->domain_event_id]);     // only the due event ran
});

it('schedules events:sweep every thirty seconds without overlapping', function () {
    // Bootstrapping the console kernel requires routes/console.php, registering the schedule entry on
    // the Schedule singleton (FoundationServiceProvider binds it shared, so this resolution and the
    // facade in the routes file are the same instance).
    app(Kernel::class)->bootstrap();

    // sole() throws if the entry is absent (or duplicated) — so finding it IS the existence proof.
    $sweep = collect(app(Schedule::class)->events())
        ->sole(fn (Event $event): bool => is_string($event->command) && str_contains($event->command, 'events:sweep'));

    expect($sweep->repeatSeconds)->toBe(30)             // everyThirtySeconds()
        ->and($sweep->withoutOverlapping)->toBeTrue();   // the double-execution guard (design D6)
});
