<?php

use App\Modules\Parties\Actions\PlaceHold;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Events\CustomerHoldPlaced;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Hold;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the Hold lifecycle PLACE path (parties-holds, design L3/L4/L5; party-registry — Requirements: Hold Lifecycle
 * and Lift Discipline, Hold Events, Hold Registry). {@see PlaceHold} is the SOLE writer of a placed `parties_holds`
 * row and the single writer of {@see CustomerHoldPlaced}. Like the evented compliance/supply Actions it injects the
 * {@see DomainEventRecorder} and resolves the operator from the {@see ActorContext} seam (System default in tests).
 *
 * The invariants this file pins for the place path: a placement creates an `active` Hold carrying the type, the
 * polymorphic scope (`scope_type` + `scope_id`), the business `reason` and the placement actor/moment (`created_at`);
 * it records exactly one PII-free `CustomerHoldPlaced` (module `parties`, entity_type `Hold`, payload asserted BY KEY
 * — PG reorders jsonb, knowledge/testing trap 3) in the SAME transaction; the unattended default actor is
 * `ActorRole::System` with a null actor id; and a system-placed Hold (null `reason`) carries the null reason through
 * to the payload (design L5 — the path `RequireKyc` reuses for the auto `kyc` Hold). The LIFT path and the per-type
 * lift discipline are pinned in task 3.2 (extending this file). RefreshDatabase per the directory convention; the
 * Action opens its OWN transaction, so the recorder's `transactionLevel() === 0` guard is satisfied by the savepoint
 * under the wrapper. Cross-engine close on PostgreSQL 17 in task 6.3.
 */
uses(RefreshDatabase::class);

it('places an active Hold recording the type, scope, reason and placement actor, and records one PII-free CustomerHoldPlaced', function () {
    // A real Customer to scope the Hold to (a within-module reference — no DB FK, design L1). Explicit PII sentinels
    // so the payload assertion can prove no personal data reaches the 10-year audit store; the factory records no
    // event, so the placement below is the only event writer in the test.
    $customer = Customer::factory()->create([
        'email' => 'hold-sentinel@example.test',
        'name' => 'Hold Sentinel',
        'phone' => '+10000000010',
    ]);

    $hold = app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Customer, $customer->id, 'manual review');

    // The returned model and the persisted row both carry the placement: active, the type/scope/reason, the
    // placement actor, and the placement moment (created_at); the lift columns stay null (un-lifted).
    $fresh = Hold::findOrFail($hold->id);
    expect($hold->status)->toBe(HoldStatus::Active)
        ->and($fresh->status)->toBe(HoldStatus::Active)
        ->and($fresh->hold_type)->toBe(HoldType::Admin)
        ->and($fresh->scope_type)->toBe(HoldScope::Customer)
        ->and($fresh->scope_id)->toBe($customer->id)
        ->and($fresh->reason)->toBe('manual review')
        ->and($fresh->placed_actor_role)->toBe(ActorRole::System)   // no operator authenticated → System (design L8)
        ->and($fresh->placed_actor_id)->toBeNull()
        ->and($fresh->created_at)->not->toBeNull()                  // the placement moment is recorded
        ->and($fresh->lifted_at)->toBeNull()
        ->and($fresh->lift_reason)->toBeNull()
        ->and($fresh->lifted_actor_role)->toBeNull();

    // Exactly one event — CustomerHoldPlaced — and nothing else (the factory records none).
    expect(DomainEvent::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerHoldPlaced::NAME)->count())->toBe(1);
    $event = DomainEvent::query()->where('name', CustomerHoldPlaced::NAME)->sole();

    // Envelope: module parties, entity Hold/<id>, resolved to the System actor (the ActorContext seam default — no
    // operator is authenticated in the test context).
    expect($event->module)->toBe('parties')
        ->and($event->entity_type)->toBe('Hold')
        ->and($event->entity_id)->toBe((string) $hold->id)
        ->and($event->actor_role)->toBe(ActorRole::System)
        ->and($event->actor_id)->toBeNull();

    // PII-free payload (decisions/2026-06-12-event-substrate-and-audit-store.md): exactly the five keys, asserted BY
    // KEY (PG reorders jsonb — trap 3), carrying the ids / enum values / business reason only — no personal data.
    expect(array_keys($event->payload))->toEqualCanonicalizing(['hold_id', 'hold_type', 'scope_type', 'scope_id', 'reason'])
        ->and($event->payload['hold_id'])->toBe($hold->id)
        ->and($event->payload['hold_type'])->toBe(HoldType::Admin->value)
        ->and($event->payload['scope_type'])->toBe(HoldScope::Customer->value)
        ->and($event->payload['scope_id'])->toBe($customer->id)
        ->and($event->payload['reason'])->toBe('manual review');
    foreach (['email', 'name', 'phone', 'date_of_birth'] as $piiKey) {
        expect($event->payload)->not->toHaveKey($piiKey);
    }
    expect(array_values($event->payload))->not->toContain('hold-sentinel@example.test')
        ->and(array_values($event->payload))->not->toContain('Hold Sentinel');
});

it('places a system Hold with a null reason and carries the null reason through to the event payload', function () {
    // The system-placement shape (design L5) — the path RequireKyc reuses for the auto `kyc` Hold (task 4.1): no
    // free-text reason, so both the row and the payload carry `reason = null` (the type IS the reason; keeps the
    // i18n invariant clean — no hardcoded reason string).
    $customer = Customer::factory()->create();

    $hold = app(PlaceHold::class)->handle(HoldType::Kyc, HoldScope::Customer, $customer->id);

    $fresh = Hold::findOrFail($hold->id);
    expect($fresh->hold_type)->toBe(HoldType::Kyc)
        ->and($fresh->status)->toBe(HoldStatus::Active)
        ->and($fresh->reason)->toBeNull();

    $event = DomainEvent::query()->where('name', CustomerHoldPlaced::NAME)->sole();
    expect($event->payload['reason'])->toBeNull()
        ->and($event->payload['hold_type'])->toBe(HoldType::Kyc->value);
});
