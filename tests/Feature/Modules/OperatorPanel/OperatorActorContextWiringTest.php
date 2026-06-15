<?php

// Task 4.2 (design D5) — the END-TO-END proof that an operator authenticated on the `operator`
// session guard flows through the ActorContext seam (wired in 4.1) into DomainEventRecorder, so the
// persisted `domain_events` envelope carries (actor_role = newco_ops, actor_id = the operator id).
// It composes the two halves proven in isolation — ActorContextTest (the seam resolves NewcoOps + id
// from a live operator session) and DomainEventRecorderTest (the recorder persists whatever role/id
// it is handed) — into the single operator-action pipeline, feeding the recorder the values RESOLVED
// from the seam (never literals) so a mis-wired seam would fail this test. The cross-engine close
// (task 6.3) re-runs the suite on PostgreSQL 17.
//
// Trait choice — DatabaseMigrations, NOT RefreshDatabase (mirrors DomainEventRecorderTest /
// HelloWorldPipelineTest, and load-bearing for the same reason): the recorder's guard checks
// `DB::transactionLevel() === 0`, which RefreshDatabase's wrapper transaction (level ≥ 1) would
// silently satisfy — hiding a missing DB::transaction() wrapper and making the emission a savepoint,
// not a real commit. DatabaseMigrations leaves each test at level 0, so the explicit transaction is
// load-bearing and the emission is a REAL commit — the faithful end-to-end shape. actingAs() sets the
// guard user in memory, so it composes with either trait.

use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Events\ActorContext;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

/**
 * Record a synthetic demo event whose actor provenance is RESOLVED from the ActorContext seam (the
 * container singleton), never passed as a literal — so the persisted envelope reflects the live auth
 * context the test set up, and the seam is resolved at call time (after actingAs), exercising its
 * lazy per-call resolution (design D5). `OperatorActorContextDemoRecorded` is a clearly-synthetic
 * name: verbatim spec event names are reserved for real module events. Wrapped in DB::transaction()
 * per the recorder's transaction contract (no registered consumer → no fan-out); returns the
 * persisted event.
 */
function recordWithResolvedActor(): DomainEvent
{
    $context = app(ActorContext::class);

    return DB::transaction(fn (): DomainEvent => app(DomainEventRecorder::class)->record(
        name: 'OperatorActorContextDemoRecorded',
        module: 'platform',
        actorRole: $context->role(),
        actorId: $context->actorId(),
        entityType: 'operator-actor-context-demo',
        entityId: '1',
        payload: ['demo' => true],
    ));
}

it('stamps an operator-authenticated emission with newco_ops and the operator id end to end', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $event = recordWithResolvedActor();

    // Re-read so the assertions exercise the persisted row (read/hydration casts), not in-memory state.
    $read = DomainEvent::findOrFail($event->id);

    expect($read->actor_role)->toBe(ActorRole::NewcoOps)              // enum cast round-trip
        ->and($read->actor_id)->toEqual($operator->getKey())         // uncast bigint; loose compare spans engines
        // The acceptance is literally the stored token `actor_role = 'newco_ops'`: assert the raw column,
        // bypassing the model cast, so the persisted value — not just the hydrated enum — is pinned.
        ->and(DB::table('domain_events')->where('id', $event->id)->value('actor_role'))
        ->toBe(ActorRole::NewcoOps->value);
});

it('stamps an unauthenticated emission with system and a null actor id (the seam tracks the context, not a hardcoded role)', function () {
    // No actingAs(): the operator guard reports a guest (non-vacuity precondition), so the seam
    // resolves (System, null) — proving the end-to-end envelope reflects the ACTUAL auth state and the
    // newco_ops above is not a constant the recorder stamps regardless.
    expect(Auth::guard('operator')->check())->toBeFalse();

    $event = recordWithResolvedActor();

    $read = DomainEvent::findOrFail($event->id);

    expect($read->actor_role)->toBe(ActorRole::System)
        ->and($read->actor_id)->toBeNull();
});
