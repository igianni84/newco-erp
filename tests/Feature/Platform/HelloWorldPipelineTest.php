<?php

use App\Platform\Audit\AuditRecord;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Events\ActorRole;
use App\Platform\Events\ConsumerRegistry;
use App\Platform\Events\DeliveryStatus;
use App\Platform\Events\Demo\DemoCommand;
use App\Platform\Events\Demo\DemoConsumer;
use App\Platform\Events\DomainEvent;
use App\Platform\Events\DomainEventRecorder;
use App\Platform\Events\EventDelivery;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

/**
 * The end-to-end "DB + event bus + audit trail" pipeline (foundations-domain-events-audit, task 5.1;
 * design D9) — the suite's single full-stack scenario, the acceptance backbone for the Hello-World
 * Demonstration requirement. It exercises the substrate's PUBLIC APIs end to end in one flow (the
 * same flow {@see DemoCommand} wraps for operators, here orchestrated directly so the pipeline test
 * is an independent witness, not a caller of the command): one transaction commits a state change +
 * a domain event + an audit record atomically; the recorder's post-commit hook then delivers the
 * event inline to the registered {@see DemoConsumer} (its DB effect visible) and the ledger row reads
 * `done`; and the recorded event is immutable (UPDATE/DELETE rejected).
 *
 * Trait choice — DatabaseMigrations, NOT RefreshDatabase: the recorder's DB::afterCommit hook and the
 * executor's per-delivery transaction need REAL commits at transactionLevel 0, which only happen
 * outside RefreshDatabase's wrapper transaction (same reasoning as the recorder/inline/sweep tests).
 */
uses(DatabaseMigrations::class);

it('commits state, event and audit atomically, delivers inline to the demo consumer, and completes the ledger', function () {
    // The demo consumer registers on the shared singleton the recorder fans out against (design D4).
    app(ConsumerRegistry::class)->register(DemoCommand::EVENT_NAME, DemoConsumer::class);

    $event = DB::transaction(function (): DomainEvent {
        // (a) state change — an idempotent platform-table write (design D9).
        DB::table('cache')->updateOrInsert(
            ['key' => DemoCommand::STATE_KEY],
            ['value' => 'demo-ran', 'expiration' => 9999999999],
        );

        // (b) the synthetic domain event — fans out a pending delivery + registers the inline hook.
        $event = app(DomainEventRecorder::class)->record(
            name: DemoCommand::EVENT_NAME,
            module: 'platform',
            actorRole: ActorRole::System,
            actorId: null,
            entityType: 'platform-demo',
            entityId: '1',
            payload: ['demo' => true, 'fx_rate' => '1.0842'],
        );

        // (c) the operator-action audit record, correlated to the event it accompanies.
        app(AuditRecorder::class)->record(
            action: DemoCommand::AUDIT_ACTION,
            module: 'platform',
            actorRole: ActorRole::System,
            actorId: null,
            entityType: 'platform-demo',
            entityId: '1',
            before: ['state' => 'absent'],
            after: ['state' => 'demo-ran'],
            authorizationBasis: 'platform-demo',
            correlationId: $event->correlation_id,
        );

        return $event;
    });

    // Atomic record: the state change, the event and the audit all persisted together.
    expect(DB::table('cache')->where('key', DemoCommand::STATE_KEY)->count())->toBe(1)
        ->and(DomainEvent::query()->whereKey($event->id)->exists())->toBeTrue()
        ->and(AuditRecord::query()
            ->where('action', DemoCommand::AUDIT_ACTION)
            ->where('correlation_id', $event->correlation_id)
            ->exists())->toBeTrue();

    // Post-commit inline delivery: the demo consumer ran (its DB effect is visible) and the ledger
    // row reads `done` with attempts 1 — exactly-once for DB effects across the whole pipeline.
    $delivery = EventDelivery::query()
        ->where('domain_event_id', $event->id)
        ->where('consumer', DemoConsumer::class)
        ->sole();

    expect($delivery->status)->toBe(DeliveryStatus::Done)
        ->and($delivery->attempts)->toEqual(1)
        ->and(DB::table('cache')->where('key', DemoConsumer::MARKER_PREFIX.$event->event_id)->count())->toBe(1);
});

it('keeps the recorded demo event immutable end to end (UPDATE and DELETE both rejected)', function () {
    $event = DB::transaction(fn (): DomainEvent => app(DomainEventRecorder::class)->record(
        name: DemoCommand::EVENT_NAME,
        module: 'platform',
        actorRole: ActorRole::System,
        actorId: null,
        entityType: 'platform-demo',
        entityId: '1',
        payload: ['demo' => true],
    ));

    // The immutability probes the demo command runs, asserted directly: the append-only log rejects
    // every mutation (the trigger raises with the stable `immutable` token, migration 000004 / D7).
    expect(fn () => DB::table('domain_events')->where('id', $event->id)->update(['name' => 'tampered']))
        ->toThrow(QueryException::class, 'immutable')
        ->and(fn () => DB::table('domain_events')->where('id', $event->id)->delete())
        ->toThrow(QueryException::class, 'immutable');

    // The row survived both probes, unchanged.
    $reread = DomainEvent::query()->whereKey($event->id)->sole();
    expect($reread->name)->toBe(DemoCommand::EVENT_NAME);
});
