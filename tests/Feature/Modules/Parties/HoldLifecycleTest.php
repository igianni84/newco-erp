<?php

use App\Modules\Parties\Actions\LiftHold;
use App\Modules\Parties\Actions\PlaceHold;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Events\CustomerHoldLifted;
use App\Modules\Parties\Events\CustomerHoldPlaced;
use App\Modules\Parties\Exceptions\IllegalHoldLift;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Hold;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the Hold lifecycle PLACE and LIFT paths (parties-holds, design L2/L3/L4/L5; party-registry — Requirements:
 * Hold Lifecycle and Lift Discipline, Hold Events, Hold Registry). {@see PlaceHold} and {@see LiftHold} are the SOLE
 * writers of a `parties_holds` row and the single writers of {@see CustomerHoldPlaced} / {@see CustomerHoldLifted}.
 * Like the evented compliance/supply Actions both inject the {@see DomainEventRecorder} and resolve the operator from
 * the {@see ActorContext} seam (System default in tests).
 *
 * The invariants this file pins for the PLACE path: a placement creates an `active` Hold carrying the type, the
 * polymorphic scope (`scope_type` + `scope_id`), the business `reason` and the placement actor/moment (`created_at`);
 * it records exactly one PII-free `CustomerHoldPlaced` (module `parties`, entity_type `Hold`, payload asserted BY KEY
 * — PG reorders jsonb, knowledge/testing trap 3) in the SAME transaction; the unattended default actor is
 * `ActorRole::System` with a null actor id; and a system-placed Hold (null `reason`) carries the null reason through
 * to the payload (design L5 — the path `RequireKyc` reuses for the auto `kyc` Hold).
 *
 * The invariants this file pins for the LIFT path + the per-type lift discipline (design L2; ADR
 * 2026-06-18-hold-lift-discipline-per-type): an operator lift of an `active` operator-liftable Hold
 * (`admin`/`fraud`/`compliance`/`credit`) moves it to `lifted`, records the lift actor/moment + `lift_reason` and
 * exactly one PII-free `CustomerHoldLifted` in the SAME transaction (the placement columns preserved); an operator
 * lift of an auto-managed type (`kyc`/`payment` — `HoldType::autoLiftable()`) is REJECTED with `IllegalHoldLift`
 * naming the `:type`, leaving the Hold `active` with no lift event; and lifting an already-`lifted` Hold is REJECTED
 * with `IllegalHoldLift` naming the `:state` (the status guard runs before the type guard), leaving state and the
 * event log unchanged.
 *
 * RefreshDatabase per the directory convention; each Action opens its OWN transaction, so the recorder's
 * `transactionLevel() === 0` guard is satisfied by the savepoint under the wrapper. Cross-engine close on
 * PostgreSQL 17 in task 6.3.
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

it('lifts an active admin Hold recording the lift actor, moment and reason, and records one PII-free CustomerHoldLifted', function () {
    // PII sentinels on the scoped Customer so the lift payload assertion can prove no personal data reaches the
    // 10-year audit store. Place first (one CustomerHoldPlaced), then lift through the operator path.
    $customer = Customer::factory()->create([
        'email' => 'lift-sentinel@example.test',
        'name' => 'Lift Sentinel',
        'phone' => '+10000000020',
    ]);

    $placed = app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Customer, $customer->id, 'manual review');
    $lifted = app(LiftHold::class)->handle($placed->id, 'review cleared');

    // The returned model and the persisted row both carry the lift: `lifted` status, the lift actor/moment/reason;
    // the placement columns (type/scope/reason/created_at) are untouched.
    $fresh = Hold::findOrFail($placed->id);
    expect($lifted->status)->toBe(HoldStatus::Lifted)
        ->and($fresh->status)->toBe(HoldStatus::Lifted)
        ->and($fresh->hold_type)->toBe(HoldType::Admin)
        ->and($fresh->scope_type)->toBe(HoldScope::Customer)
        ->and($fresh->scope_id)->toBe($customer->id)
        ->and($fresh->lifted_actor_role)->toBe(ActorRole::System)   // no operator authenticated → System (design L8)
        ->and($fresh->lifted_actor_id)->toBeNull()
        ->and($fresh->lifted_at)->not->toBeNull()                   // the lift moment is recorded
        ->and($fresh->lift_reason)->toBe('review cleared')
        ->and($fresh->reason)->toBe('manual review');              // the placement reason is preserved

    // Exactly two events now — the placement and the lift — and the lift is exactly one CustomerHoldLifted.
    expect(DomainEvent::query()->count())->toBe(2)
        ->and(DomainEvent::query()->where('name', CustomerHoldLifted::NAME)->count())->toBe(1);
    $event = DomainEvent::query()->where('name', CustomerHoldLifted::NAME)->sole();

    // Envelope: module parties, entity Hold/<id>, resolved to the System actor (the ActorContext seam default).
    expect($event->module)->toBe('parties')
        ->and($event->entity_type)->toBe('Hold')
        ->and($event->entity_id)->toBe((string) $placed->id)
        ->and($event->actor_role)->toBe(ActorRole::System)
        ->and($event->actor_id)->toBeNull();

    // PII-free payload: exactly the five keys, asserted BY KEY (PG reorders jsonb — trap 3), carrying the ids / enum
    // values / business lift_reason only — no personal data.
    expect(array_keys($event->payload))->toEqualCanonicalizing(['hold_id', 'hold_type', 'scope_type', 'scope_id', 'lift_reason'])
        ->and($event->payload['hold_id'])->toBe($placed->id)
        ->and($event->payload['hold_type'])->toBe(HoldType::Admin->value)
        ->and($event->payload['scope_type'])->toBe(HoldScope::Customer->value)
        ->and($event->payload['scope_id'])->toBe($customer->id)
        ->and($event->payload['lift_reason'])->toBe('review cleared');
    foreach (['email', 'name', 'phone', 'date_of_birth'] as $piiKey) {
        expect($event->payload)->not->toHaveKey($piiKey);
    }
    expect(array_values($event->payload))->not->toContain('lift-sentinel@example.test')
        ->and(array_values($event->payload))->not->toContain('Lift Sentinel');
});

it('lifts every operator-liftable Hold type, recording one CustomerHoldLifted each', function (HoldType $type) {
    // The spec scenario "An operator lifts an operator-liftable Hold" names `admin` (resp. `fraud`, `compliance`,
    // `credit`); the detailed assertions ride the `admin` case above, so this pins the other three operator-liftable
    // types lift freely too (null reason — the system-placement shape).
    $customer = Customer::factory()->create();
    $placed = app(PlaceHold::class)->handle($type, HoldScope::Customer, $customer->id);

    $lifted = app(LiftHold::class)->handle($placed->id);

    expect($lifted->status)->toBe(HoldStatus::Lifted)
        ->and(Hold::findOrFail($placed->id)->status)->toBe(HoldStatus::Lifted)
        ->and(DomainEvent::query()->where('name', CustomerHoldLifted::NAME)->count())->toBe(1);
})->with([
    'fraud' => HoldType::Fraud,
    'compliance' => HoldType::Compliance,
    'credit' => HoldType::Credit,
]);

it('rejects an operator-lift of an auto-managed Hold type, leaving it active with no lift event', function (HoldType $type) {
    // The operator path refuses an auto-managed type (`kyc`/`payment` — HoldType::autoLiftable()): those lift only on
    // their system clearing signal. The thrown message names the offending :type token — interpolated, not literal in
    // the template (task 1.3 keeps the token out of the copy), so this proves the rule fired on THIS type.
    $customer = Customer::factory()->create();
    $placed = app(PlaceHold::class)->handle($type, HoldScope::Customer, $customer->id);

    expect(fn () => app(LiftHold::class)->handle($placed->id))
        ->toThrow(IllegalHoldLift::class, $type->value);

    // No state change, no event: the Hold stays `active` with null lift columns, and only the placement event exists.
    $fresh = Hold::findOrFail($placed->id);
    expect($fresh->status)->toBe(HoldStatus::Active)
        ->and($fresh->lifted_at)->toBeNull()
        ->and($fresh->lifted_actor_role)->toBeNull()
        ->and($fresh->lift_reason)->toBeNull()
        ->and(DomainEvent::query()->where('name', CustomerHoldLifted::NAME)->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(1);   // only the CustomerHoldPlaced from the placement
})->with([
    'kyc' => HoldType::Kyc,
    'payment' => HoldType::Payment,
]);

it('rejects lifting an already-lifted Hold, leaving state and the event log unchanged', function () {
    // First lift succeeds (admin is operator-liftable); the second is rejected by the STATUS guard, which runs before
    // the type discipline — an out-of-state lift is illegal whatever the type. The message names the offending :state
    // token (`lifted`, interpolated — the template carries no literal `lifted` word).
    $customer = Customer::factory()->create();
    $placed = app(PlaceHold::class)->handle(HoldType::Admin, HoldScope::Customer, $customer->id, 'manual review');

    app(LiftHold::class)->handle($placed->id, 'review cleared');

    expect(fn () => app(LiftHold::class)->handle($placed->id, 'second attempt'))
        ->toThrow(IllegalHoldLift::class, HoldStatus::Lifted->value);

    // State and the event log are unchanged: still `lifted` with the FIRST lift's reason, and still exactly one
    // CustomerHoldLifted (the failed second lift recorded nothing and rolled back).
    $fresh = Hold::findOrFail($placed->id);
    expect($fresh->status)->toBe(HoldStatus::Lifted)
        ->and($fresh->lift_reason)->toBe('review cleared')   // NOT 'second attempt'
        ->and(DomainEvent::query()->where('name', CustomerHoldLifted::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->count())->toBe(2);       // 1 placed + 1 lifted
});
