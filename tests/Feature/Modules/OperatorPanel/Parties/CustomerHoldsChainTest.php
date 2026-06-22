<?php

// Task 5.1 (operator-console-parties-holds; design D5/D6/D7/D9; the change's CLOSING integration proof for the
// Customer console's HOLD surface) — one feature test driving the WHOLE Holds slice end-to-end through the PAGE +
// WIDGET (not the raw domain Actions), exactly as a human operator would demo it. It composes the proven vehicles —
// placeHold via `callAction('placeHold', …)` on {@see ViewCustomer}, per-row lift via `callTableAction('lift', $hold,
// …)` on {@see CustomerHoldsTable} — into the full place → suspend → partial-lift → restore chain, then asserts the
// EMERGENT event SET over the entire run (the closing-integration rule, knowledge/testing/rules.md). It proves what
// no single per-task test asserts over the COMPOSED chain:
//   1. the Hold→status coupling is DOMAIN-OWNED and ADDITIVE (design D7) — the console invokes ONLY place/lift, yet
//      the emergent set carries the coupling's own CustomerSuspended (first Hold on an `active` Customer) and exactly
//      one CustomerReactivated (lifting the LAST covering Hold), never a status verb the console called;
//   2. the multi-Hold partial-lift case is correct — two concurrent Customer-scope Holds both cover the Customer, so
//      lifting the first leaves it `suspended` (NO restore), and only lifting the last restores it (BR-K-Hold-1);
//   3. the from-state pre-check holds — placing a Hold on a `pending` Customer records the Hold and drives NO
//      transition (the status FSM stays independent of onboarding);
//   4. lift discipline is DEFENSE IN DEPTH (design D6) — the surface HIDES the per-row lift on an auto-managed `kyc`
//      Hold AND the domain INDEPENDENTLY rejects an out-of-band operator lift (IllegalHoldLift), Hold unchanged;
//   5. the emergent set is EXACTLY 3× CustomerHoldPlaced + 1× CustomerSuspended + 2× CustomerHoldLifted + 1×
//      CustomerReactivated — both Customers are profile-less (the factory co-provisions none), so the suspend/restore
//      Profile cascade stays silent (design D7); the factory `kyc` Hold and the rejected (rolled-back) lift add
//      nothing; every recorded event carries the operator audit envelope (module `parties`, newco_ops, the operator).
//
// THE kyc REJECT IS THE ONLY DIRECT-DOMAIN CALL (lessons.md 2026-06-22): a per-row lift HIDDEN by visibility is
// unreachable through any Filament test helper (visibility is re-resolved server-side on every call, a hidden action
// never mounts), and the lift's `->visible()` predicate is the EXACT complement of LiftHold's rejection conditions —
// so the widget's `action_failed` branch is structurally unreachable for a lift rejection. The console CAN evidence
// both halves the discipline rests on: the surface HIDES lift on the `kyc` row (`assertTableActionHidden`), and the
// domain rejects an operator lift invoked straight on the Action (`toThrow(IllegalHoldLift)`). The kit's
// RuntimeException→`action_failed` surfacing is a shared guarantee whose SUCCESS half (`hold_lifted`) fires through
// this very widget in the lift steps above.
//
// DatabaseMigrations (mirroring CustomerConsoleChainTest + the per-task CustomerHoldsConsoleTest): each console action
// drives a domain action that opens its OWN DB::transaction, so the DomainEventRecorder's in-transaction append
// commits for real (RefreshDatabase would wrap every write in a never-committed outer transaction). The factory
// bypasses the actions — it records NO event and co-provisions no Account/Profile, so the only events are the console
// verbs'. Parties enums/models/actions import freely here: the {Models, Actions, Enums} import-boundary carve-out
// governs OperatorPanel PRODUCTION code, not tests.
//
// Green on SQLite AND PG17 (the change's PG17 gate, task 5.2): the uncast `actor_id` bigint reads back as a numeric
// string on PostgreSQL, so it is asserted with loose `toEqual`; events are asserted BY NAME + envelope, never a
// byte-compare of stored jsonb (PG reorders keys).

use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Pages\ViewCustomer;
use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Widgets\CustomerHoldsTable;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Actions\LiftHold;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Events\CustomerHoldLifted;
use App\Modules\Parties\Events\CustomerHoldPlaced;
use App\Modules\Parties\Events\CustomerReactivated;
use App\Modules\Parties\Events\CustomerSuspended;
use App\Modules\Parties\Exceptions\IllegalHoldLift;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Hold;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

it('drives the full Hold place→suspend→partial-lift→restore chain through the console — the pending no-op, the kyc reject, and the emergent newco_ops event set', function () {
    // ONE operator drives the whole demo — the Hold surface is single-operator (no separation of duties), so no
    // distinct lineage is needed. Every event below must carry this operator's id (actor_role newco_ops), resolved
    // by the domain actions from the `operator` guard; the console constructs no envelope itself.
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // ══ PART (a) — place two covering Holds on an ACTIVE Customer; the coupling suspends on the first ════════════
    // An `active` Customer is in the suspendable from-state, and the factory co-provisions no Profile — so the
    // Hold→`suspended` coupling fires for the Customer scope alone (no ProfileSuspended cascade noise). The console
    // invokes ONLY PlaceHold; the suspension is the domain coupling's own additive behaviour (design D7).
    $customerA = Customer::factory()->create(['status' => CustomerStatus::Active]);

    // 1. place `admin` → CustomerHoldPlaced + the coupling's CustomerSuspended; the active Customer → `suspended`.
    Livewire::test(ViewCustomer::class, ['record' => $customerA->id])
        ->callAction('placeHold', [
            'hold_type' => HoldType::Admin->value,
            'scope_type' => HoldScope::Customer->value,
            'reason' => 'manual review',
        ])
        ->assertNotified((string) __('operator_console.customer.notifications.hold_placed'));
    expect(Customer::findOrFail($customerA->id)->status)->toBe(CustomerStatus::Suspended);

    // 2. place a second (`fraud`) Hold on the now-`suspended` Customer → CustomerHoldPlaced, but the from-state
    //    pre-check skips the re-suspend (BR-K-Hold-1 admits concurrent Holds), so NO second CustomerSuspended.
    Livewire::test(ViewCustomer::class, ['record' => $customerA->id])
        ->callAction('placeHold', [
            'hold_type' => HoldType::Fraud->value,
            'scope_type' => HoldScope::Customer->value,
            'reason' => 'fraud review',
        ])
        ->assertNotified((string) __('operator_console.customer.notifications.hold_placed'));
    expect(Customer::findOrFail($customerA->id)->status)->toBe(CustomerStatus::Suspended)
        ->and(DomainEvent::query()->where('name', CustomerSuspended::NAME)->count())->toBe(1);

    // The two Holds the console wrote (scoped to this Customer's own id; B's Holds come later with a different id).
    $adminHold = Hold::query()->where('scope_id', $customerA->id)->where('hold_type', HoldType::Admin)->sole();
    $fraudHold = Hold::query()->where('scope_id', $customerA->id)->where('hold_type', HoldType::Fraud)->sole();

    // ══ PART (b) — partial lift (no restore) then the last lift (restore) through the per-row table action ════════
    // Reuse one widget render across both lifts (the proven CustomerHoldsConsoleTest idiom); the table query + the
    // per-row visibility re-resolve on each Livewire request.
    $tableA = Livewire::test(CustomerHoldsTable::class, ['record' => $customerA]);

    // 3. lift `admin` (operator-liftable → `lift` visible) → one CustomerHoldLifted; the `fraud` Hold STILL covers
    //    the Customer, so the restore coupling does NOT fire — the Customer stays `suspended`, NO CustomerReactivated.
    //    Pinning the no-restore here localises design D7: were the restore to fire early, this fails at the cause.
    $tableA->callTableAction('lift', $adminHold, ['lift_reason' => 'admin cleared'])
        ->assertNotified((string) __('operator_console.customer.notifications.hold_lifted'));
    expect(Customer::findOrFail($customerA->id)->status)->toBe(CustomerStatus::Suspended)
        ->and(DomainEvent::query()->where('name', CustomerHoldLifted::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerReactivated::NAME)->count())->toBe(0);

    // 4. lift the LAST covering Hold (`fraud`) → a second CustomerHoldLifted AND exactly one CustomerReactivated in
    //    the same transaction (no active Hold remains to cover the Customer); the Customer → `active`.
    $tableA->callTableAction('lift', $fraudHold, ['lift_reason' => 'fraud cleared'])
        ->assertNotified((string) __('operator_console.customer.notifications.hold_lifted'));
    expect(Customer::findOrFail($customerA->id)->status)->toBe(CustomerStatus::Active)
        ->and(DomainEvent::query()->where('name', CustomerHoldLifted::NAME)->count())->toBe(2)
        ->and(DomainEvent::query()->where('name', CustomerReactivated::NAME)->count())->toBe(1);

    // ══ PART (c) — a Hold on a PENDING Customer records the Hold but drives no transition ═════════════════════════
    // A fresh factory Customer is born `pending` — NOT in the suspendable from-state, so the coupling's from-state
    // pre-check records the Hold and drives NO transition. No `reason` submitted → the optional operand normalises to
    // NULL (never '').
    $customerB = Customer::factory()->create();
    Livewire::test(ViewCustomer::class, ['record' => $customerB->id])
        ->callAction('placeHold', [
            'hold_type' => HoldType::Admin->value,
            'scope_type' => HoldScope::Customer->value,
        ])
        ->assertNotified((string) __('operator_console.customer.notifications.hold_placed'));
    expect(Customer::findOrFail($customerB->id)->status)->toBe(CustomerStatus::Pending);

    // ══ PART (d) — lift discipline is defense in depth on an auto-managed `kyc` Hold ══════════════════════════════
    // A bare `active` `kyc` Hold via the factory (no coupling, no event): `kyc` is auto-managed (HoldType::
    // autoLiftable()) — it lifts ONLY on its system clearing signal, never by hand.
    $kycHold = Hold::factory()->create([
        'hold_type' => HoldType::Kyc,
        'status' => HoldStatus::Active,
        'scope_type' => HoldScope::Customer,
        'scope_id' => $customerB->id,
    ]);

    // The SURFACE half — the kyc row renders but its per-row `lift` is HIDDEN ({@see CustomerHoldsTable::
    // isOperatorLiftable} excludes auto-managed types).
    Livewire::test(CustomerHoldsTable::class, ['record' => $customerB])
        ->assertCanSeeTableRecords([$kycHold])
        ->assertTableActionHidden('lift', record: $kycHold);

    // The ENFORCEMENT half — an operator lift that BYPASSES the hidden UI (invoked straight on the domain) is
    // rejected by LiftHold (IllegalHoldLift::autoManaged); the rejection rolls back, leaving the Hold untouched and
    // recording no lift event.
    expect(fn () => app(LiftHold::class)->handle($kycHold->id))->toThrow(IllegalHoldLift::class);
    expect($kycHold->refresh()->status)->toBe(HoldStatus::Active)
        ->and($kycHold->lifted_actor_role)->toBeNull()
        ->and($kycHold->lifted_at)->toBeNull();

    // ══ Emergent event-SET proof over the WHOLE demo ═════════════════════════════════════════════════════════════
    // (a) the emergent set is EXACTLY the seven console writes: 3× CustomerHoldPlaced (admin/fraud on A, admin on B),
    //     1× CustomerSuspended (first Hold on `active` A), 2× CustomerHoldLifted (admin + fraud), 1× CustomerReactivated
    //     (lifting the last covering Hold). Both Customers are profile-less, so the suspend/restore Profile cascade
    //     stayed silent; the factory `kyc` Hold and the rolled-back operator lift added nothing.
    expect(DomainEvent::query()->pluck('name')->all())
        ->toEqualCanonicalizing([
            CustomerHoldPlaced::NAME,
            CustomerHoldPlaced::NAME,
            CustomerHoldPlaced::NAME,
            CustomerSuspended::NAME,
            CustomerHoldLifted::NAME,
            CustomerHoldLifted::NAME,
            CustomerReactivated::NAME,
        ]);

    // (b) EVERY recorded event is a Parties console-driven write carrying the operator audit envelope — module
    //     `parties`, actor_role newco_ops, a non-null operator actor (no System-actor projection rows exist).
    $events = DomainEvent::query()->get();
    expect($events)->toHaveCount(7);
    foreach ($events as $event) {
        expect($event->module)->toBe('parties')
            ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
            ->and($event->actor_id)->not->toBeNull();
    }

    // (c) …and the per-event envelope is concrete on representative writes spanning BOTH entity types — a Hold event
    //     (entity_type `Hold`, entity_id the Hold id) and a coupling status event (entity_type `Customer`, entity_id
    //     the Customer id) — for BOTH the place and the lift verb. Loose `toEqual` on actor_id is the proven idiom:
    //     the uncast bigint reads back as a numeric string on PG.
    $placedAdmin = DomainEvent::query()->where('name', CustomerHoldPlaced::NAME)->where('entity_id', (string) $adminHold->id)->sole();
    expect($placedAdmin->entity_type)->toBe('Hold')
        ->and($placedAdmin->entity_id)->toBe((string) $adminHold->id)
        ->and($placedAdmin->actor_id)->toEqual($operator->id);

    $suspended = DomainEvent::query()->where('name', CustomerSuspended::NAME)->sole();
    expect($suspended->entity_type)->toBe('Customer')
        ->and($suspended->entity_id)->toBe((string) $customerA->id)
        ->and($suspended->actor_id)->toEqual($operator->id);

    $liftedAdmin = DomainEvent::query()->where('name', CustomerHoldLifted::NAME)->where('entity_id', (string) $adminHold->id)->sole();
    expect($liftedAdmin->entity_type)->toBe('Hold')
        ->and($liftedAdmin->entity_id)->toBe((string) $adminHold->id)
        ->and($liftedAdmin->actor_id)->toEqual($operator->id);

    $reactivated = DomainEvent::query()->where('name', CustomerReactivated::NAME)->sole();
    expect($reactivated->entity_type)->toBe('Customer')
        ->and($reactivated->entity_id)->toBe((string) $customerA->id)
        ->and($reactivated->actor_id)->toEqual($operator->id);
});
