<?php

// Tasks 2.1–3.3 (operator-console-parties-kyc-sanctions; design D2/D3/D4/D5/D6/D7/D8) — the Customer console's
// KYC + sanctions compliance-WRITE surface on ViewCustomer. The three form-less KYC verbs (requireKyc,
// recordKycVerified, recordKycRejected) and the one form-bearing sanctions verb (recordScreening) the page APPENDS
// to its SurfacesDomainActions-built header-action array (design D2/D3), each routing through a Parties domain
// action by the customer id and NEVER writing the model itself (the no-Eloquent-write rule).
//
// THE KYC VERBS ARE VISIBILITY-GATED to their legal `kyc_status` from-state (design D4): requireKyc iff
// NULL/not_required, recordKycVerified/recordKycRejected iff pending. Because the visibility predicate is the EXACT
// COMPLEMENT of the domain from-state guard, a rejected transition is UNREACHABLE through the surface — the verb is
// simply hidden; its reject is proven by a domain toThrow + assertActionHidden (task 2.3), never an action_failed
// the page can't raise (the Filament hidden-action landmine, lessons.md 2026-06-22). Task 2.1 pins the VISIBILITY
// contract; 2.2 (below) adds the write-through + auto-Hold coupling, 2.3 the reject-floor, 3.x the sanctions form.
//
// THE KYC VERBS ARE EVENT-SILENT (design D7): the only events are the coupled CustomerHoldPlaced/Lifted (from the
// auto-Hold) + CustomerSuspended/Reactivated (from the coupling); RecordKycRejected records NOTHING. No
// CustomerKyc* event exists — the catalog names none (asserted in 2.2/2.3).
//
// DatabaseMigrations (mirroring ProducerKycConsoleTest + CustomerLifecycleConsoleTest): each console action drives
// a real domain action opening its OWN DB::transaction, so the in-transaction event append commits for real
// (RefreshDatabase would wrap every write in a never-committed outer transaction). The factory bypasses the
// actions → records no event, co-provisions no Account/Profile. Parties enums/models are imported freely here: the
// {Models, Actions} import-boundary carve-out governs OperatorPanel PRODUCTION code, not tests.

use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Pages\ViewCustomer;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Actions\RequireKyc;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Events\CustomerHoldLifted;
use App\Modules\Parties\Events\CustomerHoldPlaced;
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

it('shows requireKyc only from a requirable kyc_status — NULL or not_required (design D4)', function (?KycStatus $from, bool $visible) {
    actingAs(Operator::factory()->create(), 'operator');

    // requireKyc OPENS the KYC FSM (→ pending); it is reachable only from un-screened (NULL — DEC-071) or the
    // explicit not_required. The verb is visible iff kycRequirable() holds — the complement of RequireKyc's guard.
    $customer = Customer::factory()->create(['kyc_status' => $from]);

    $component = Livewire::test(ViewCustomer::class, ['record' => $customer->id]);

    if ($visible) {
        $component->assertActionVisible('requireKyc');
    } else {
        $component->assertActionHidden('requireKyc');
    }
})->with([
    'never-screened (NULL) → visible' => [null, true],
    'not_required → visible' => [KycStatus::NotRequired, true],
    'pending → hidden' => [KycStatus::Pending, false],
    'verified → hidden' => [KycStatus::Verified, false],
    'rejected → hidden' => [KycStatus::Rejected, false],
]);

it('shows recordKycVerified and recordKycRejected only from pending (design D4)', function (?KycStatus $from, bool $visible) {
    actingAs(Operator::factory()->create(), 'operator');

    // Verify and reject are each reachable ONLY from `pending` (§ 9.1); each is visible iff kycPending() — the
    // complement of RecordKycVerified's / RecordKycRejected's domain guard. Both are visible together when pending.
    $customer = Customer::factory()->create(['kyc_status' => $from]);

    $component = Livewire::test(ViewCustomer::class, ['record' => $customer->id]);

    if ($visible) {
        $component->assertActionVisible('recordKycVerified')
            ->assertActionVisible('recordKycRejected');
    } else {
        $component->assertActionHidden('recordKycVerified')
            ->assertActionHidden('recordKycRejected');
    }
})->with([
    'pending → visible' => [KycStatus::Pending, true],
    'never-screened (NULL) → hidden' => [null, false],
    'not_required → hidden' => [KycStatus::NotRequired, false],
    'verified → hidden' => [KycStatus::Verified, false],
    'rejected → hidden' => [KycStatus::Rejected, false],
]);

it('requires KYC through the console on an active Customer — pending + kyc_required, an active kyc Hold, suspended; one CustomerHoldPlaced + one CustomerSuspended, zero KYC events (design D7)', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    // An `active`, un-screened (NULL kyc_status) Customer: requireKyc is VISIBLE (kycRequirable holds), and `active`
    // is the suspendable from-state, so RequireKyc's auto-Hold coupling fires (it places a Customer-scope `kyc` Hold
    // → SuspendCustomer in the SAME transaction — the console invokes ONLY RequireKyc). The factory co-provisions no
    // Profile, so the suspension cascade is silent (no ProfileSuspended) and CustomerSuspended is the only status event.
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        // callAction asserts-visible-first, then drives the form-less verb into RequireKyc by the customer id — the
        // console writes nothing itself (the no-Eloquent-write rule).
        ->callAction('requireKyc')
        ->assertNotified((string) __('operator_console.customer.notifications.kyc_required'));

    // The KYC FSM opened: `pending` + the administratively-set `kyc_required` flag (RequireKyc is its sole writer).
    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->kyc_status)->toBe(KycStatus::Pending)
        ->and($fresh->kyc_required)->toBeTrue()
        // The Hold→`suspended` coupling drove the active Customer to `suspended` (domain-owned, additive — design D7).
        ->and($fresh->status)->toBe(CustomerStatus::Suspended);

    // Exactly one Hold — the system-placed Customer-scope `kyc` Hold (reason NULL: the type IS the reason — design L5).
    $hold = Hold::query()->sole();
    expect($hold->hold_type)->toBe(HoldType::Kyc)
        ->and($hold->scope_type)->toBe(HoldScope::Customer)
        ->and($hold->scope_id)->toBe($customer->id)
        ->and($hold->status)->toBe(HoldStatus::Active)
        ->and($hold->reason)->toBeNull();

    // The KYC verb is EVENT-SILENT (design D7): the only events are the coupled CustomerHoldPlaced (entity Hold) and
    // CustomerSuspended (entity Customer), each carrying the operator audit envelope resolved from the `operator`
    // guard — the console constructs no envelope itself (the heterogeneous entity_type the chain test re-proves at 4.1).
    $placed = DomainEvent::query()->where('name', CustomerHoldPlaced::NAME)->sole();
    expect($placed->module)->toBe('parties')
        ->and($placed->entity_type)->toBe('Hold')
        ->and($placed->entity_id)->toBe((string) $hold->id)
        ->and($placed->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($placed->actor_id)->toEqual($operator->id);  // loose: PG returns a numeric string for the bigint

    $suspended = DomainEvent::query()->where('name', CustomerSuspended::NAME)->sole();
    expect($suspended->module)->toBe('parties')
        ->and($suspended->entity_type)->toBe('Customer')
        ->and($suspended->entity_id)->toBe((string) $customer->id)
        ->and($suspended->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($suspended->actor_id)->toEqual($operator->id);

    // … and NO KYC-named event exists — the catalog names none (design D7); do not invent a CustomerKyc* event.
    expect(DomainEvent::query()->where('name', 'like', 'CustomerKyc%')->count())->toBe(0);
});

it('records KYC verified through the console on a require-suspended Customer — verified, the kyc Hold lifted, reactivated; one CustomerHoldLifted + one CustomerReactivated, zero KYC events', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // Arrange the verify precondition through the REAL RequireKyc coupling, NOT the bare factory (which records no
    // event and never suspends): on an `active` Customer, RequireKyc moves kyc_status → `pending`, auto-places the
    // `kyc` Hold and — the place coupling — suspends the Customer (CustomerHoldPlaced + CustomerSuspended). This is the
    // live post-activation re-screen path the restore side of the verify coupling exists for (design L6).
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);
    app(RequireKyc::class)->handle($customer->id);

    expect(Customer::findOrFail($customer->id)->status)->toBe(CustomerStatus::Suspended);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        // recordKycVerified is visible (kycPending holds) and drives RecordKycVerified by the customer id.
        ->callAction('recordKycVerified')
        ->assertNotified((string) __('operator_console.customer.notifications.kyc_verified'));

    // KYC cleared to `verified`; the system-lift auto-lifted the `kyc` Hold; and — no OTHER Hold covering — the
    // restore side of the coupling reactivated the suspended Customer (design L2/L6).
    $fresh = Customer::findOrFail($customer->id);
    expect($fresh->kyc_status)->toBe(KycStatus::Verified)
        ->and($fresh->status)->toBe(CustomerStatus::Active);

    // The single `kyc` Hold the require placed is now `lifted` (the contrast with reject, which leaves it active).
    $hold = Hold::query()->sole();
    expect($hold->hold_type)->toBe(HoldType::Kyc)
        ->and($hold->status)->toBe(HoldStatus::Lifted);

    // The verify is EVENT-SILENT for KYC (design D7): exactly the coupled CustomerHoldLifted + CustomerReactivated,
    // and NO KYC-named event (the require's CustomerHoldPlaced/CustomerSuspended are the arrange's, asserted above).
    expect(DomainEvent::query()->where('name', CustomerHoldLifted::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', CustomerReactivated::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', 'like', 'CustomerKyc%')->count())->toBe(0);
});

it('records KYC rejected through the console on a pending Customer — rejected, the kyc Hold left active, no event at all (audit-only — design D7)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A `pending`-KYC Customer with an active Customer-scope `kyc` Hold, stood up via the BARE factories (no coupling,
    // no event): RecordKycRejected is audit-only — it records NOTHING and must LEAVE the Hold in place (the contrast
    // with verify, which system-lifts it — § 9.1). Arranging through the factories keeps the event log empty, so
    // "no event at all" is a clean post-condition (no baseline arithmetic needed).
    $customer = Customer::factory()->create(['kyc_status' => KycStatus::Pending]);
    $kyc = Hold::factory()->create([
        'hold_type' => HoldType::Kyc,
        'status' => HoldStatus::Active,
        'scope_type' => HoldScope::Customer,
        'scope_id' => $customer->id,
    ]);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        // recordKycRejected is visible (kycPending holds) and drives RecordKycRejected by the customer id.
        ->callAction('recordKycRejected')
        ->assertNotified((string) __('operator_console.customer.notifications.kyc_rejected'));

    // KYC moved to the blocking `rejected` state …
    expect(Customer::findOrFail($customer->id)->kyc_status)->toBe(KycStatus::Rejected)
        // … the `kyc` Hold is LEFT in place (reject never lifts it — § 9.1) …
        ->and($kyc->refresh()->status)->toBe(HoldStatus::Active)
        // … and the reject recorded NOTHING (audit-only; RecordKycRejected touches no recorder — design D7).
        ->and(DomainEvent::query()->count())->toBe(0);
});
