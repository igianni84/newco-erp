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
// contract; 2.2 the write-through + auto-Hold coupling; 2.3 (below) the reject-floor + the no-waive guard (design D8)
// + the KYC↔sanctions independence check (design D7); 3.1 (below) the sanctions form's verdict + record-dependent
// trigger_source options; 3.2/3.3 its write-through + the onboarding-first floor (upcoming).
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
use App\Modules\Parties\Actions\RecordKycRejected;
use App\Modules\Parties\Actions\RecordKycVerified;
use App\Modules\Parties\Actions\RequireKyc;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Events\CustomerHoldLifted;
use App\Modules\Parties\Events\CustomerHoldPlaced;
use App\Modules\Parties\Events\CustomerReactivated;
use App\Modules\Parties\Events\CustomerSuspended;
use App\Modules\Parties\Exceptions\IllegalKycTransition;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Hold;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Filament\Forms\Components\Select;
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

// ── 2.3 · The reject FLOOR (design D4 — the hidden-action landmine) ──────────────────────────────────────────────
// Each KYC verb's ->visible() predicate is the EXACT COMPLEMENT of its domain from-state guard, so an out-of-state
// transition is UNREACHABLE through the surface: the verb is simply HIDDEN, and a hidden Filament action can't be
// mounted/invoked (callAction asserts-visible-FIRST; mountAction is a server-side no-op — task 1.2's pinned landmine).
// So — unlike the always-present Producer KYC verbs whose reject surfaces as `action_failed` (cf. ProducerKycConsoleTest's
// "surfaces an illegal KYC transition…") — the Customer verbs' reject is proven the only way the surface allows: the
// surface HIDES it (assertActionHidden) AND the domain INDEPENDENTLY rejects an out-of-band call (a domain toThrow),
// with kyc_status and the (empty) event log unchanged — NEVER an assertNotified(action_failed) the page can't raise.
// Bare-factory Customers (no Hold, no event) keep "unchanged" a clean post-condition (no baseline arithmetic).

it('proves the requireKyc reject floor — hidden out of its from-state AND the domain rejects an out-of-band call, kyc_status + the event log unchanged (design D4)', function (KycStatus $from) {
    actingAs(Operator::factory()->create(), 'operator');

    // requireKyc OPENS the FSM (legal only from `not_required`/NULL); every advanced state is out-of-from-state.
    $customer = Customer::factory()->create(['kyc_status' => $from]);

    // Half 1 — the surface HIDES the verb (callAction would assert-visible-FIRST and fail).
    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->assertActionHidden('requireKyc');

    // Half 2 — the domain FLOOR: an out-of-band call throws IllegalKycTransition (imported freely in the test) and
    // rolls back BEFORE any write (RequireKyc guards the from-state before placing the `kyc` Hold).
    expect(fn () => app(RequireKyc::class)->handle($customer->id))->toThrow(IllegalKycTransition::class);

    // Nothing moved: kyc_status is exactly as arranged and the event log is still empty (the transaction rolled back).
    expect(Customer::findOrFail($customer->id)->kyc_status)->toBe($from)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'pending → hidden + rejected' => [KycStatus::Pending],
    'verified → hidden + rejected' => [KycStatus::Verified],
    'rejected → hidden + rejected' => [KycStatus::Rejected],
]);

it('proves the recordKycVerified reject floor — hidden out of pending AND the domain rejects an out-of-band call, unchanged (design D4)', function (?KycStatus $from) {
    actingAs(Operator::factory()->create(), 'operator');

    // recordKycVerified is legal ONLY from `pending`; the surface hides it for every other state — including NULL
    // (un-screened — DEC-071), which the domain renders with the `unset` sentinel when it throws.
    $customer = Customer::factory()->create(['kyc_status' => $from]);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->assertActionHidden('recordKycVerified');

    expect(fn () => app(RecordKycVerified::class)->handle($customer->id))->toThrow(IllegalKycTransition::class);

    expect(Customer::findOrFail($customer->id)->kyc_status)->toBe($from)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'not_required → hidden + rejected' => [KycStatus::NotRequired],
    'verified → hidden + rejected' => [KycStatus::Verified],
    'rejected → hidden + rejected' => [KycStatus::Rejected],
    'never-screened (NULL) → hidden + rejected' => [null],
]);

it('proves the recordKycRejected reject floor — hidden out of pending AND the domain rejects an out-of-band call, unchanged (design D4)', function (?KycStatus $from) {
    actingAs(Operator::factory()->create(), 'operator');

    // recordKycRejected is legal ONLY from `pending` too; the surface hides it for every other state — including NULL.
    $customer = Customer::factory()->create(['kyc_status' => $from]);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->assertActionHidden('recordKycRejected');

    expect(fn () => app(RecordKycRejected::class)->handle($customer->id))->toThrow(IllegalKycTransition::class);

    expect(Customer::findOrFail($customer->id)->kyc_status)->toBe($from)
        ->and(DomainEvent::query()->count())->toBe(0);
})->with([
    'not_required → hidden + rejected' => [KycStatus::NotRequired],
    'verified → hidden + rejected' => [KycStatus::Verified],
    'rejected → hidden + rejected' => [KycStatus::Rejected],
    'never-screened (NULL) → hidden + rejected' => [null],
]);

it('exposes no Customer KYC waive verb — there is no WaiveCustomerKyc Action (design D8)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // `pending` is where a waive would be most tempting (the Producer console offers waiveKyc from any outstanding
    // state), but the Customer KYC FSM has NO waive: only WaiveProducerKyc exists (producer-only — design D8). The
    // console registers neither `waiveKyc` nor any waive id, so the action is ABSENT (assertActionDoesNotExist), not
    // merely hidden — there is nothing to evaluate a ->visible() closure on.
    $customer = Customer::factory()->create(['kyc_status' => KycStatus::Pending]);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->assertActionDoesNotExist('waiveKyc')
        ->assertActionDoesNotExist('waive');
});

it('keeps sanctions_status untouched through a console require→verify KYC cycle — no screening event (design D7 independence)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // An `active` Customer already screened `passed`: KYC and sanctions are SEPARATE FSMs (§ 9.4), so a full
    // require→verify KYC cycle driven through the console must move ONLY kyc_status and its coupled Hold/status events
    // — never sanctions_status or the screening timestamps, and recording NO screening event. (sanctions_status is set
    // on the fixture via the operand enum, imported freely — the carve-out governs production code, not tests.)
    $customer = Customer::factory()->create([
        'status' => CustomerStatus::Active,
        'sanctions_status' => SanctionsStatus::Passed,
    ]);

    // require → `pending` (the coupled `kyc` Hold suspends the active Customer). A FRESH mount re-reads the now-`pending`
    // record so recordKycVerified is visible → `verified` (the system-lift reactivates the Customer). Two mounts: the
    // second verb's visibility depends on the first having committed (each Livewire::test re-reads the record).
    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->callAction('requireKyc')
        ->assertNotified((string) __('operator_console.customer.notifications.kyc_required'));
    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->callAction('recordKycVerified')
        ->assertNotified((string) __('operator_console.customer.notifications.kyc_verified'));

    $fresh = Customer::findOrFail($customer->id);
    // KYC ran its full cycle and the coupling restored the Customer to `active` …
    expect($fresh->kyc_status)->toBe(KycStatus::Verified)
        ->and($fresh->status)->toBe(CustomerStatus::Active)
        // … but the sanctions FSM is exactly as arranged — never screened by the cycle (last_screening_at still NULL),
        // and NO screening event fired (all four screening event names match the LIKE — design D7 independence).
        ->and($fresh->sanctions_status)->toBe(SanctionsStatus::Passed)
        ->and($fresh->last_screening_at)->toBeNull()
        ->and(DomainEvent::query()->where('name', 'like', 'Customer%creening%')->count())->toBe(0);
});

// ── 3.1 · The sanctions-screening form (design D3/D6) ─────────────────────────────────────────────────────────────
// recordScreening is a BESPOKE form header action (the placeHold precedent — NOT a form-less verb): a screening
// carries a SanctionsStatus verdict + a ScreeningTriggerSource operand. The verdict Select offers all four states;
// the trigger_source Select offers a RECORD-DEPENDENT subset — `onboarding` ONLY while the Customer has never been
// screened (design D6 — onboarding-is-first), `compliance_ad_hoc` always (the deferred cadence/aml_threshold
// automation sources are never operator-offered, § 9.5). The write-through into RecordCustomerScreening lands in 3.2;
// here the form only collects, so the action body is a no-op and this test only mounts-and-inspects the schema.

it('exposes the recordScreening form — all four SanctionsStatus verdicts, with trigger_source dropping onboarding once screened (design D6)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A never-screened Customer: the bare factory sets none of the additive screening columns, so last_screening_at
    // is NULL — trigger_source offers BOTH onboarding (the first-screen source) and compliance_ad_hoc.
    $neverScreened = Customer::factory()->create();

    Livewire::test(ViewCustomer::class, ['record' => $neverScreened->id])
        // recordScreening is a HEADER form action on the page (always present — no from-state gate); mount it to
        // inspect its schema (the form only collects here — the write-through lands in 3.2).
        ->mountAction('recordScreening')
        // verdict exposes EXACTLY the four SanctionsStatus operand-enum tokens (value-keyed, in enum order).
        ->assertFormFieldExists('verdict', fn (Select $field): bool => array_keys($field->getOptions())
            === array_map(static fn (SanctionsStatus $status): string => $status->value, SanctionsStatus::cases()))
        // trigger_source on a never-screened Customer offers onboarding FIRST, then compliance_ad_hoc (design D6).
        ->assertFormFieldExists('trigger_source', fn (Select $field): bool => array_keys($field->getOptions())
            === ['onboarding', 'compliance_ad_hoc']);

    // An already-screened Customer (last_screening_at stamped): onboarding-is-first means the option DROPS — only
    // compliance_ad_hoc remains offerable, the EXACT COMPLEMENT of RecordCustomerScreening's onboarding-already-
    // screened floor (the option-set narrows; the domain still enforces — the surface-hides + domain-enforces split).
    $screened = Customer::factory()->create(['last_screening_at' => now()]);

    Livewire::test(ViewCustomer::class, ['record' => $screened->id])
        ->mountAction('recordScreening')
        ->assertFormFieldExists('trigger_source', fn (Select $field): bool => array_keys($field->getOptions())
            === ['compliance_ad_hoc']);
});
