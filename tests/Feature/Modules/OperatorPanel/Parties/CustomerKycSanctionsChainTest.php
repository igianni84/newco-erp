<?php

// Task 4.1 (operator-console-parties-kyc-sanctions; design D7/D11; the change's CLOSING integration proof for the
// Customer console's KYC + sanctions surface) — one feature test driving the WHOLE slice end-to-end through the PAGE
// + WIDGET (not the raw domain Actions), exactly as a human operator would demo it. It composes the proven vehicles —
// the three form-less KYC verbs + the form-bearing recordScreening via `callAction(...)` on {@see ViewCustomer}, and
// the shipped per-row Holds table {@see CustomerHoldsTable} the Holds slice already wired — into the full
// require → suspend → verify → reactivate → onboarding-screen chain, then asserts the EMERGENT event SET over the
// entire run (the closing-integration rule, knowledge/testing/rules.md). It proves what no single per-task test
// asserts over the COMPOSED chain:
//   1. the KYC verbs are EVENT-SILENT (design D7) — the console invokes ONLY requireKyc / recordKycVerified, yet the
//      emergent set carries the coupling's OWN CustomerHoldPlaced / CustomerSuspended (require auto-places a `kyc` Hold
//      on an `active` Customer → the place coupling suspends) and CustomerHoldLifted / CustomerReactivated (verify
//      auto-lifts the last covering Hold → the restore coupling reactivates), and NEVER a KYC-named event (the catalog
//      names none — do not invent CustomerKycVerified);
//   2. the cross-slice link to the shipped Holds widget holds — the auto-placed `kyc` Hold RENDERS in the Holds table,
//      its per-row `lift` is HIDDEN (auto-managed — {@see CustomerHoldsTable::isOperatorLiftable} excludes `kyc`, the
//      Holds slice's discipline), and on verify the SAME row flips to `lifted` (the cross-slice contract the Holds
//      slice left a forward note for: RequireKyc auto-places, RecordKycVerified auto-lifts — verifiable THROUGH the
//      table this console already renders);
//   3. KYC and sanctions are INDEPENDENT FSMs (§ 9.4, design D7) — the onboarding screening that closes the chain moves
//      ONLY the sanctions fields (sanctions_status / screening_trigger_source / last_screening_at) and leaves the
//      kyc_status the cycle drove to `verified` untouched, recording exactly one CustomerOnboardingScreeningPassed;
//   4. the emergent set is EXACTLY 1× CustomerHoldPlaced + 1× CustomerSuspended + 1× CustomerHoldLifted + 1×
//      CustomerReactivated + 1× CustomerOnboardingScreeningPassed — the Customer is PROFILE-LESS (the factory
//      co-provisions none), so the suspend/reactivate Profile cascade stays silent (design D7) and the count is exact;
//      every recorded event carries the operator audit envelope (module `parties`, newco_ops, the operator id).
//
// THE ENVELOPE IS HETEROGENEOUS: the two Hold events carry entity_type `Hold` (the Hold id); the two status events +
// the screening event carry entity_type `Customer` (the Customer id). The console constructs no envelope itself — the
// domain actions resolve it from the `operator` guard (one operator drives the whole demo; the KYC + sanctions surface
// is single-operator, no separation of duties).
//
// DatabaseMigrations (mirroring CustomerHoldsChainTest + the per-task CustomerKycSanctionsConsoleTest): each console
// action drives a domain action that opens its OWN DB::transaction, so the DomainEventRecorder's in-transaction append
// commits for real (RefreshDatabase would wrap every write in a never-committed outer transaction). The factory
// bypasses the actions — it records NO event and co-provisions no Account/Profile, so the only events are the console
// verbs'. Parties enums/models import freely here: the {Models, Actions, Enums} import-boundary carve-out governs
// OperatorPanel PRODUCTION code, not tests.
//
// Green on SQLite AND PG17 (the change's PG17 gate, task 4.2): the uncast `actor_id` bigint reads back as a numeric
// string on PostgreSQL, so it is asserted with loose `toEqual`; events are asserted BY NAME + envelope, never a
// byte-compare of stored jsonb (PG reorders keys).

use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Pages\ViewCustomer;
use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Widgets\CustomerHoldsTable;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Enums\ScreeningTriggerSource;
use App\Modules\Parties\Events\CustomerHoldLifted;
use App\Modules\Parties\Events\CustomerHoldPlaced;
use App\Modules\Parties\Events\CustomerOnboardingScreeningPassed;
use App\Modules\Parties\Events\CustomerReactivated;
use App\Modules\Parties\Events\CustomerSuspended;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Hold;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

it('drives the full KYC require→verify + onboarding-screen chain through the page + Holds widget — the auto-Hold coupling, the lifted-row flip, and the emergent newco_ops event set carrying NO KYC event (design D7/D11)', function () {
    // ONE operator drives the whole demo — the KYC + sanctions surface is single-operator (no separation of duties),
    // so no distinct lineage is needed. Every event below must carry this operator's id (actor_role newco_ops),
    // resolved by the domain actions from the `operator` guard; the console constructs no envelope itself.
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // ══ PART (a) — requireKyc on an ACTIVE, never-screened Customer; the coupling auto-places a `kyc` Hold + suspends ══
    // An `active`, un-screened (NULL kyc_status) Customer: requireKyc is VISIBLE (kycRequirable holds), and `active` is
    // the suspendable from-state, so RequireKyc's auto-Hold coupling fires (it places a Customer-scope `kyc` Hold →
    // SuspendCustomer in the SAME transaction — the console invokes ONLY RequireKyc). The factory co-provisions no
    // Profile, so the suspension cascade is silent (no ProfileSuspended) and CustomerSuspended is the only status event.
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->callAction('requireKyc')
        ->assertNotified((string) __('operator_console.customer.notifications.kyc_required'));

    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->kyc_status)->toBe(KycStatus::Pending)
        // the Hold→`suspended` coupling drove the active Customer to `suspended` (domain-owned, additive — design D7).
        ->and($fresh->status)->toBe(CustomerStatus::Suspended);

    // The single auto-placed Hold — the system-placed Customer-scope `kyc` Hold (the only Hold in the whole run).
    $kycHold = Hold::query()->sole();
    expect($kycHold->hold_type)->toBe(HoldType::Kyc)
        ->and($kycHold->status)->toBe(HoldStatus::Active);

    // WIDGET (the cross-slice link): the auto-placed `kyc` Hold RENDERS in the shipped Holds table, and its per-row
    // `lift` is HIDDEN — `kyc` is auto-managed ({@see CustomerHoldsTable::isOperatorLiftable} excludes it), so the
    // operator can't hand-lift the Hold the KYC FSM manages (the Holds slice's lift discipline, re-proven here).
    Livewire::test(CustomerHoldsTable::class, ['record' => $customer])
        ->assertCanSeeTableRecords([$kycHold])
        ->assertTableActionHidden('lift', record: $kycHold);

    // ══ PART (b) — recordKycVerified auto-lifts the `kyc` Hold and the restore coupling reactivates ═══════════════════
    // recordKycVerified is visible (kycPending holds) and drives RecordKycVerified by the customer id: kyc_status →
    // `verified`, the system-lift lifts the `kyc` Hold, and — no OTHER Hold covering — the restore side of the coupling
    // reactivates the suspended Customer (CustomerHoldLifted + CustomerReactivated). The console invokes ONLY the verb.
    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->callAction('recordKycVerified')
        ->assertNotified((string) __('operator_console.customer.notifications.kyc_verified'));

    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->kyc_status)->toBe(KycStatus::Verified)
        ->and($fresh->status)->toBe(CustomerStatus::Active)
        // the SAME `kyc` Hold row the widget showed `active` has flipped to `lifted` (the cross-slice auto-lift).
        ->and($kycHold->refresh()->status)->toBe(HoldStatus::Lifted);

    // WIDGET again: the now-`lifted` row still LISTS (the table shows active AND lifted Holds), and its `lift` stays
    // HIDDEN — a lifted Hold is doubly non-operator-liftable (no longer `active`, and still auto-managed).
    Livewire::test(CustomerHoldsTable::class, ['record' => $customer])
        ->assertCanSeeTableRecords([$kycHold])
        ->assertTableActionHidden('lift', record: $kycHold);

    // ══ PART (c) — recordScreening (onboarding / passed) closes the chain; KYC stays untouched (independence) ═════════
    // last_screening_at is still NULL (KYC never touches it), so `onboarding` is a legal first-screen source. The
    // screening moves ONLY the sanctions fields and records exactly one CustomerOnboardingScreeningPassed — the KYC FSM
    // the cycle drove to `verified` is left exactly as it is (§ 9.4 — the two FSMs are independent, design D7).
    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->callAction('recordScreening', data: ['verdict' => 'passed', 'trigger_source' => 'onboarding'])
        ->assertNotified((string) __('operator_console.customer.notifications.screening_recorded'));

    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->sanctions_status)->toBe(SanctionsStatus::Passed)
        ->and($fresh->screening_trigger_source)->toBe(ScreeningTriggerSource::Onboarding)
        ->and($fresh->last_screening_at)->not->toBeNull()
        // independence (design D7): the screening never touched the KYC FSM the require→verify cycle drove to `verified`.
        ->and($fresh->kyc_status)->toBe(KycStatus::Verified);

    // ══ Emergent event-SET proof over the WHOLE demo ═════════════════════════════════════════════════════════════════
    // (a) the emergent set is EXACTLY the five domain-owned writes the composed chain produced — the auto-Hold
    //     coupling's CustomerHoldPlaced + CustomerSuspended (require), CustomerHoldLifted + CustomerReactivated
    //     (verify), and the one CustomerOnboardingScreeningPassed (screen). It is a multiset carrying NO KYC event —
    //     the KYC verbs are event-silent (design D7) — and the profile-less Customer kept the Profile cascade silent.
    expect(DomainEvent::query()->pluck('name')->all())
        ->toEqualCanonicalizing([
            CustomerHoldPlaced::NAME,
            CustomerSuspended::NAME,
            CustomerHoldLifted::NAME,
            CustomerReactivated::NAME,
            CustomerOnboardingScreeningPassed::NAME,
        ])
        ->toHaveCount(5);

    // … and NO KYC-named event exists in the set — the catalog names none (design D7); do not invent a CustomerKyc* event.
    expect(DomainEvent::query()->where('name', 'like', 'CustomerKyc%')->count())->toBe(0);

    // (b) EVERY recorded event is a Parties console-driven write carrying the operator audit envelope — module
    //     `parties`, actor_role newco_ops, a non-null operator actor (no System-actor projection rows exist).
    $events = DomainEvent::query()->get();
    expect($events)->toHaveCount(5);
    foreach ($events as $event) {
        expect($event->module)->toBe('parties')
            ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
            ->and($event->actor_id)->not->toBeNull();
    }

    // (c) …and the per-event envelope is concrete on EVERY write, spanning BOTH entity types — the two Hold events
    //     (entity_type `Hold`, entity_id the Hold id) and the three Customer events (entity_type `Customer`, entity_id
    //     the Customer id). Loose `toEqual` on actor_id is the proven idiom: the uncast bigint reads back as a numeric
    //     string on PG.
    $placed = DomainEvent::query()->where('name', CustomerHoldPlaced::NAME)->sole();
    expect($placed->entity_type)->toBe('Hold')
        ->and($placed->entity_id)->toBe((string) $kycHold->id)
        ->and($placed->actor_id)->toEqual($operator->id);

    $lifted = DomainEvent::query()->where('name', CustomerHoldLifted::NAME)->sole();
    expect($lifted->entity_type)->toBe('Hold')
        ->and($lifted->entity_id)->toBe((string) $kycHold->id)
        ->and($lifted->actor_id)->toEqual($operator->id);

    $suspended = DomainEvent::query()->where('name', CustomerSuspended::NAME)->sole();
    expect($suspended->entity_type)->toBe('Customer')
        ->and($suspended->entity_id)->toBe((string) $customer->id)
        ->and($suspended->actor_id)->toEqual($operator->id);

    $reactivated = DomainEvent::query()->where('name', CustomerReactivated::NAME)->sole();
    expect($reactivated->entity_type)->toBe('Customer')
        ->and($reactivated->entity_id)->toBe((string) $customer->id)
        ->and($reactivated->actor_id)->toEqual($operator->id);

    $screened = DomainEvent::query()->where('name', CustomerOnboardingScreeningPassed::NAME)->sole();
    expect($screened->entity_type)->toBe('Customer')
        ->and($screened->entity_id)->toBe((string) $customer->id)
        ->and($screened->actor_id)->toEqual($operator->id);
});
