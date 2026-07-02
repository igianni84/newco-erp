<?php

use App\Modules\Parties\Actions\ActivateProducer;
use App\Modules\Parties\Actions\ActivateProducerAgreement;
use App\Modules\Parties\Actions\CloseClub;
use App\Modules\Parties\Actions\CreateClub;
use App\Modules\Parties\Actions\CreateProducer;
use App\Modules\Parties\Actions\CreateProducerAgreement;
use App\Modules\Parties\Actions\RetireProducer;
use App\Modules\Parties\Actions\SunsetClub;
use App\Modules\Parties\Actions\TerminateProducerAgreement;
use App\Modules\Parties\Enums\ClubRegistrationFlowType;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Enums\ProducerAgreementStatus;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Events\ClubClosed;
use App\Modules\Parties\Events\ClubCreated;
use App\Modules\Parties\Events\ClubSunset;
use App\Modules\Parties\Events\ProducerActivated;
use App\Modules\Parties\Events\ProducerAgreementActivated;
use App\Modules\Parties\Events\ProducerAgreementCreated;
use App\Modules\Parties\Events\ProducerAgreementSuperseded;
use App\Modules\Parties\Events\ProducerAgreementTerminated;
use App\Modules\Parties\Events\ProducerCreated;
use App\Modules\Parties\Events\ProducerRetired;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Producer;
use App\Modules\Parties\Models\ProducerAgreement;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The full-chain integration proof for the Parties SUPPLY-SIDE lifecycle (parties-producer-lifecycle task 5.2;
 * party-registry — Requirements: Producer Lifecycle, ProducerAgreement Lifecycle, Club Lifecycle, Supply-Side
 * Lifecycle Events, and the MODIFIED "Birth States Recorded, Lifecycle Transitions Deferred"). Where every other
 * lifecycle test in this directory pins ONE transition in isolation, this one drives the WHOLE supply side through
 * its real Actions — create (via the spine Create* seams) → activate the Producer → activate an agreement, renew
 * it (superseding the original), terminate the renewal → sunset + close one Club → retire the Producer (cascading
 * sunset onto the still-active Club) — and asserts the emergent contract of the slice as a whole:
 *   - EXACTLY the seven verbatim § 15.3/15.4/15.5 supply-side events are recorded (ProducerActivated,
 *     ProducerRetired, ProducerAgreementActivated, ProducerAgreementSuperseded, ProducerAgreementTerminated,
 *     ClubSunset, ClubClosed), each with its expected count for this chain, layered atop the five spine *Created
 *     events the Create* seams emit — and NO other name (the distinct name set is pinned);
 *   - the two DERIVED chains are causally threaded (design L5): the cascade ClubSunset on the still-active Club
 *     carries the ProducerRetired event's id as its causation_id and shares its correlation_id; the
 *     ProducerAgreementSuperseded carries the renewal-activation event's id as its causation_id and shares its
 *     correlation_id — while the STANDALONE ClubSunset and the original's own activation are roots;
 *   - the DEMAND SIDE stays inert through the whole supply-side chain: it records no CustomerActivated /
 *     ProfileActivated / OriginatingClubLocked / CustomerSegmentChanged (the runtime assertion below) — even though
 *     the activation event classes and the demand-side activation Actions now ship (parties-membership-activation);
 *     and — reflecting the Actions namespace — the non-Create Actions are exactly the supply-side, compliance,
 *     Hold-registry and demand-side activation transitions (the exact-set whitelist below).
 *
 * The companion SpineCreationChainTest (which asserts the CREATION chain emits no lifecycle event) and the two
 * architecture tests (ModuleBoundariesTest, ModulePersistenceConventionsTest) stay GREEN UNAMENDED — every
 * reference here is within Module K, and this change adds no model.
 *
 * This is the cross-engine gate: this file and the whole Parties suite are verified green on SQLite AND on a local
 * PostgreSQL 17 before the change is declared complete (knowledge/testing/rules.md). Portability: the event set is
 * asserted BY NAME and payloads BY KEY (never a byte-compare of stored jsonb — PG reorders keys, trap 3); the
 * causation_id (int) / correlation_id (string) envelope columns are observed through the model so they round-trip
 * on both engines. RefreshDatabase per the directory convention; each Action opens its OWN DB::transaction, so the
 * recorder's `transactionLevel() === 0` guard is satisfied by the savepoint under the wrapper.
 */
uses(RefreshDatabase::class);

/**
 * Drives the ENTIRE supply-side lifecycle through the real Create* + transition Actions in dependency order and
 * returns the created entities by key. Every leg goes through the genuine Action (its own DB::transaction +
 * recorder), exactly as production would — so every assertion below observes real substrate behaviour, never a
 * factory shortcut (factories bypass the Actions and record no event).
 *
 * End states the chain reaches: Producer `retired`; the standalone Club `closed`; the cascade Club `sunset`; the
 * original agreement `superseded`; the renewal agreement `terminated`.
 *
 * @return array{
 *     producer: Producer,
 *     clubStandalone: Club,
 *     clubCascade: Club,
 *     agreementOriginal: ProducerAgreement,
 *     agreementRenewal: ProducerAgreement,
 * }
 */
function runSupplyLifecycleChain(): array
{
    // 1. Onboard the Producer (born `draft`) and activate it (`draft → active`) — ProducerActivated (root).
    $producer = app(CreateProducer::class)->handle(
        name: 'Chateau Margaux',
        region: 'Bordeaux',
        country: 'France',
    );
    app(ActivateProducer::class)->handle($producer->id);

    // 2. The Producer operates two Clubs (both born `active`): one wound down standalone, one left active to be
    //    swept by the retirement cascade.
    $clubStandalone = app(CreateClub::class)->handle(
        displayName: 'Margaux Cellar Club',
        producerId: $producer->id,
        registrationFlowType: ClubRegistrationFlowType::ApplicationWithApproval,
        fee: Money::of(25000, Currency::EUR),
    );
    $clubCascade = app(CreateClub::class)->handle(
        displayName: 'Margaux Reserve Club',
        producerId: $producer->id,
        registrationFlowType: ClubRegistrationFlowType::ApplicationWithApproval,
        fee: Money::of(50000, Currency::EUR),
    );

    // 3. Activate a Producer-wide agreement (club_id null), then a renewal in the SAME scope that supersedes it
    //    (ProducerAgreementActivated → ProducerAgreementSuperseded), then terminate the renewal. This walks the
    //    full agreement FSM draft → active → {superseded | terminated} in one scope.
    $agreementOriginal = app(CreateProducerAgreement::class)->handle(
        producerId: $producer->id,
        clubId: null,
        termStart: CarbonImmutable::parse('2026-01-01'),
        termEnd: CarbonImmutable::parse('2026-12-31'),
        settlementCadence: 'monthly',
    );
    app(ActivateProducerAgreement::class)->handle($agreementOriginal->id);   // root activation, supersedes nothing

    $agreementRenewal = app(CreateProducerAgreement::class)->handle(
        producerId: $producer->id,
        clubId: null,
        termStart: CarbonImmutable::parse('2027-01-01'),
        termEnd: CarbonImmutable::parse('2027-12-31'),
        settlementCadence: 'monthly',
    );
    app(ActivateProducerAgreement::class)->handle($agreementRenewal->id);    // supersedes the original (same scope)
    app(TerminateProducerAgreement::class)->handle($agreementRenewal->id);

    // 4. Wind down the standalone Club through its full FSM (`active → sunset → closed`).
    app(SunsetClub::class)->handle($clubStandalone->id);
    app(CloseClub::class)->handle($clubStandalone->id);

    // 5. Retire the Producer (`active → retired`) — ProducerRetired (cascade root), cascading sunset onto the
    //    still-active Club (the closed one is skipped — the cascade is idempotent over already-transitioned Clubs).
    app(RetireProducer::class)->handle($producer->id);

    return [
        'producer' => $producer,
        'clubStandalone' => $clubStandalone,
        'clubCascade' => $clubCascade,
        'agreementOriginal' => $agreementOriginal,
        'agreementRenewal' => $agreementRenewal,
    ];
}

it('drives every supply-side entity to its terminal state through the real transition Actions', function () {
    $chain = runSupplyLifecycleChain();

    // Re-fetch through the models so the assertions exercise the hydration casts, not the in-memory create()
    // values. The chain reached every terminal/derived state in all three FSMs.
    expect(Producer::findOrFail($chain['producer']->id)->status)->toBe(ProducerStatus::Retired)
        ->and(Club::findOrFail($chain['clubStandalone']->id)->status)->toBe(ClubStatus::Closed)
        ->and(Club::findOrFail($chain['clubCascade']->id)->status)->toBe(ClubStatus::Sunset)
        ->and(ProducerAgreement::findOrFail($chain['agreementOriginal']->id)->status)->toBe(ProducerAgreementStatus::Superseded)
        ->and(ProducerAgreement::findOrFail($chain['agreementRenewal']->id)->status)->toBe(ProducerAgreementStatus::Terminated);
});

it('records exactly the seven supply-side lifecycle events with the expected counts, atop the spine creation events', function () {
    runSupplyLifecycleChain();

    // The seven verbatim supply-side lifecycle events (§ 15.3/15.4/15.5), each with its expected count for this
    // chain — asserted BY NAME (knowledge/testing trap 3: never byte-compare PG jsonb).
    $expected = [
        ProducerActivated::NAME => 1,
        ProducerRetired::NAME => 1,
        ProducerAgreementActivated::NAME => 2,    // the original + the renewal
        ProducerAgreementSuperseded::NAME => 1,   // the original, superseded by the renewal
        ProducerAgreementTerminated::NAME => 1,   // the renewal
        ClubSunset::NAME => 2,                    // the standalone sunset + the retirement-cascade sunset
        ClubClosed::NAME => 1,
    ];
    foreach ($expected as $name => $count) {
        expect(DomainEvent::query()->where('name', $name)->count())->toBe($count);
    }

    // The chain is built through the Create* seams, so the five spine *Created events are recorded too (one
    // Producer, two Clubs, two ProducerAgreements). They are CREATION events, not lifecycle transitions — present
    // by construction, and pinned here so the chain's shape can't drift.
    expect(DomainEvent::query()->where('name', ProducerCreated::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ClubCreated::NAME)->count())->toBe(2)
        ->and(DomainEvent::query()->where('name', ProducerAgreementCreated::NAME)->count())->toBe(2);

    // Exactly these ten distinct names and NO other — pinned so no surprise lifecycle event (and crucially no
    // demand-side event) can slip in. 5 creation + 9 lifecycle = 14 rows total.
    expect(DomainEvent::query()->pluck('name')->unique()->values()->all())->toEqualCanonicalizing([
        ProducerCreated::NAME, ClubCreated::NAME, ProducerAgreementCreated::NAME,
        ProducerActivated::NAME, ProducerRetired::NAME,
        ProducerAgreementActivated::NAME, ProducerAgreementSuperseded::NAME, ProducerAgreementTerminated::NAME,
        ClubSunset::NAME, ClubClosed::NAME,
    ]);
    expect(DomainEvent::query()->count())->toBe(14);

    // Every event is tagged module `parties` and resolved to the System actor (the ActorContext seam default — no
    // operator is authenticated in the test context).
    expect(DomainEvent::query()->where('module', 'parties')->count())->toBe(14)
        ->and(DomainEvent::query()->get()->every(fn (DomainEvent $event): bool => $event->actor_role === ActorRole::System))->toBeTrue();
});

it('threads the two derived chains and leaves the standalone transitions as root events', function () {
    $chain = runSupplyLifecycleChain();

    // --- Cascade chain (design L5; spec "Cascade events are causally linked to the retirement") ---
    // ProducerRetired is the cascade ROOT: no cause, self-correlated.
    $retired = DomainEvent::query()->where('name', ProducerRetired::NAME)->sole();
    expect($retired->causation_id)->toBeNull()
        ->and($retired->correlation_id)->toBe($retired->event_id);

    // The cascade ClubSunset (on the still-active Club) carries the retirement's id and shares its correlation.
    $cascadeSunset = DomainEvent::query()
        ->where('name', ClubSunset::NAME)
        ->where('entity_id', (string) $chain['clubCascade']->id)
        ->sole();
    expect($cascadeSunset->causation_id)->toBe($retired->id)
        ->and($cascadeSunset->correlation_id)->toBe($retired->correlation_id);

    // The STANDALONE ClubSunset (operator-driven, before the retirement) is a ROOT — it is NOT part of the
    // cascade, so it carries no cause and is self-correlated. This is what distinguishes the single ClubSunset
    // writer's two invocation modes (design L6).
    $standaloneSunset = DomainEvent::query()
        ->where('name', ClubSunset::NAME)
        ->where('entity_id', (string) $chain['clubStandalone']->id)
        ->sole();
    expect($standaloneSunset->causation_id)->toBeNull()
        ->and($standaloneSunset->correlation_id)->toBe($standaloneSunset->event_id);

    // --- Supersession chain (design L5; spec "Supersession events pair old and new") ---
    // The renewal's activation is the root of the supersession; the original's supersession is caused by it.
    $renewalActivated = DomainEvent::query()
        ->where('name', ProducerAgreementActivated::NAME)
        ->where('entity_id', (string) $chain['agreementRenewal']->id)
        ->sole();
    $superseded = DomainEvent::query()->where('name', ProducerAgreementSuperseded::NAME)->sole();

    expect($superseded->entity_id)->toBe((string) $chain['agreementOriginal']->id)   // the supersession is ABOUT the original
        ->and($superseded->causation_id)->toBe($renewalActivated->id)
        ->and($superseded->correlation_id)->toBe($renewalActivated->correlation_id);

    // The pair references old + new in payload: the activation points new → old, the supersession points old → new.
    expect($renewalActivated->payload['supersedes'])->toBe($chain['agreementOriginal']->id)
        ->and($superseded->payload['superseded_by'])->toBe($chain['agreementRenewal']->id);

    // The original's OWN activation was a ROOT (it superseded nothing in its then-empty scope).
    $originalActivated = DomainEvent::query()
        ->where('name', ProducerAgreementActivated::NAME)
        ->where('entity_id', (string) $chain['agreementOriginal']->id)
        ->sole();
    expect($originalActivated->payload['supersedes'])->toBeNull()
        ->and($originalActivated->causation_id)->toBeNull()
        ->and($originalActivated->correlation_id)->toBe($originalActivated->event_id);
});

it('records zero demand-side lifecycle events — the demand side stays inert through the whole supply-side chain', function () {
    runSupplyLifecycleChain();

    // No demand-side lifecycle / state-change event is recorded by the entire supply-side chain (party-registry
    // MODIFIED — the demand-side change owns these). Asserted by EXACT name, NOT `like '%Activated%'` (which would
    // match the legitimate Producer/Agreement activations).
    foreach ([
        'CustomerActivated', 'AccountActivated', 'ProfileActivated', 'ProfileApproved',
        'OriginatingClubLocked', 'CustomerSegmentChanged',
    ] as $name) {
        expect(DomainEvent::query()->where('name', $name)->count())->toBe(0);
    }

    // The supply side never touches a Customer / Account / Profile, so no event carries those entity types.
    expect(DomainEvent::query()->whereIn('entity_type', ['Customer', 'Account', 'Profile'])->count())->toBe(0);
});

it('exposes the supply-side, compliance, Hold and demand-side activation transition Actions — the still-deferred demand-side status transitions and their event types stay absent (the scope guard)', function () {
    // Reflect the Parties Actions namespace: every Action is a flat class file directly under Actions/.
    $files = glob(app_path('Modules/Parties/Actions/*.php')) ?: [];
    expect($files)->not->toBeEmpty();   // the walk must have run — never a vacuous pass

    $actions = array_map(static fn (string $file): string => basename($file, '.php'), $files);

    // Each file maps to a real class in the Actions namespace (genuine reflection of the namespace, not a
    // string-only scan).
    foreach ($actions as $name) {
        expect(class_exists('App\\Modules\\Parties\\Actions\\'.$name))->toBeTrue();
    }

    // The six supply-side transition Actions this change shipped all exist...
    $supplySideTransitions = [
        'ActivateProducer', 'RetireProducer',
        'ActivateProducerAgreement', 'TerminateProducerAgreement',
        'SunsetClub', 'CloseClub',
    ];
    foreach ($supplySideTransitions as $transition) {
        expect($actions)->toContain($transition);
    }

    // ...alongside the compliance transition Actions (parties-compliance — the KYC/sanctions FSMs are SEPARATE
    // from the Customer/Producer status FSMs, § 9.1/§ 9.4, so they are legitimate non-Create transitions). This
    // list grows as the compliance slice lands its Actions (Customer KYC here; Producer KYC + sanctions
    // screening follow), each task declaring its transitions in this guard.
    $complianceTransitions = [
        'RequireKyc', 'RecordKycVerified', 'RecordKycRejected',
        'RequireProducerKyc', 'RecordProducerKycVerified', 'RecordProducerKycRejected', 'WaiveProducerKyc',
        'RecordCustomerScreening',
    ];

    // ...and the Hold lifecycle Actions (parties-holds — the unified Hold registry's place/lift). A Hold is neither
    // a supply-side status transition nor a compliance-screening transition; crucially, placing/lifting a Hold
    // performs NO Customer/Account/Profile STATUS transition (the Hold→`suspended` coupling is a deferred
    // demand-side seam — proposal slice boundary), so these registry Actions do not breach the scope guard this
    // test pins. Both place (PlaceHold) and lift (LiftHold) shipped with the slice's Action tasks.
    $holdTransitions = [
        'PlaceHold',
        'LiftHold',
    ];

    // ...and the demand-side activation transitions (parties-membership-activation — the one retained producer
    // write, L-PP, plus activation). `ApproveProfile` (`applied → approved`, plus the in-tx Originating-Club
    // one-shot lock) and `DeclineProfile` (`applied → rejected`, terminal) are audit-only on the Profile (§ 15.2
    // names no ProfileApproved/ProfileRejected — the approve path's lone event is the conditional
    // OriginatingClubLocked); `ActivateProfile` (`approved → active`) records `ProfileActivated` and
    // `ActivateCustomer` (`pending → active`, behind the composite onboarding gate) records `CustomerActivated`.
    $demandSideTransitions = [
        'ApproveProfile',
        'DeclineProfile',
        'ActivateProfile',
        'ActivateCustomer',
    ];

    // ...and the demand-side STATUS transitions (parties-membership-suspension — the post-activation status edges off
    // `active`). Task 2.1 ships the Profile suspend/restore pair: `SuspendProfile` (`active → suspended`) records
    // `ProfileSuspended` and `ReactivateProfile` (`suspended → active`) records `ProfileReactivated`, both
    // state-preserving (design L9). Task 2.2 adds the Profile lapse/renew pair: `LapseProfile` (`active → lapsed`,
    // stamping `lapsed_at`) records `ProfileExpired` (NOT `ProfileLapsed` — the § 15.2 naming trap, L3) and
    // `RenewProfile` (`lapsed → active` within the 30-day grace, DEC-034) records `ProfileRenewed`. Task 2.3 adds the
    // Profile cancel/deactivate set: `CancelProfile` (`active | lapsed → cancelled`, writing the optional
    // `cancellation_reason`) is AUDIT-ONLY — it records NO event (§ 15.2 names no `ProfileCancelled`, design L2) but is
    // still a transition Action, so it IS whitelisted here; `DeactivateProfile` (`active → inactive`) records
    // `ProfileInactive`. Task 3.1 adds the Customer suspend/restore cascade: `SuspendCustomer` (`active → suspended`,
    // cascading `ProfileSuspended` to the Customer's `Active` Profiles as causation children — design L11) and
    // `ReactivateCustomer` (`suspended → active`, cascade-restoring only the Profiles no longer covered by an active
    // Hold). Task 3.2 completes the set with the Customer terminal `CloseCustomer` (`active | suspended → closed`,
    // recording `CustomerClosed` but — unlike suspension — NOT cascading to Profiles, § 15.1, design L7) and the whole
    // Account FSM `SuspendAccount`/`ReactivateAccount`/`CloseAccount` (`active → suspended → active → closed`),
    // AUDIT-ONLY — they record NO event (§ 15 names no Account-family event, design L8) but are still transition
    // Actions, so they ARE whitelisted here. With 3.2 the demand-side status-transition set is COMPLETE; the coupling
    // tasks 4.x wire `PlaceHold`/`LiftHold` into these Actions but add NO new Action class. There is still NO
    // `ActivateAccount` (the Account is born `active` — design L8; it stays absent forever).
    $demandSideStatusTransitions = [
        'SuspendProfile',
        'ReactivateProfile',
        'LapseProfile',
        'RenewProfile',
        'CancelProfile',
        'DeactivateProfile',
        'SuspendCustomer',
        'ReactivateCustomer',
        'CloseCustomer',
        'SuspendAccount',
        'ReactivateAccount',
        'CloseAccount',
    ];

    // ...and the Club Credit within-module writers (change club-credit — the per-Profile prepayment FSM
    // `active → redeemed | forfeited`). `IssueClubCredit` (task 2.1) creates an `active` credit; it is named
    // `Issue*` not `Create*`, so the Create-filter below treats it as a transition Action and it MUST be whitelisted
    // here. These writers are AUDIT-ONLY — § 11.4 makes `ClubCreditAccrued`/`Applied`/`Restored`/`Forfeited` (and the
    // `MembershipFeePaid` trigger) Module-E/-S financial events (DEC-018: `ClubCreditApplied` from Module S), so
    // they record state and emit NO Parties event — but they are
    // still non-Create Actions, so they ARE whitelisted (mirroring the audit-only Account/CancelProfile entries). With
    // task 4.2 the FOUR-writer set is COMPLETE: `IssueClubCredit` (2.1) creates the `active` credit, `ApplyClubCredit`
    // (3.1) is the redemption decrement / `active → redeemed` writer, `ForfeitClubCredit` (4.1) the `active →
    // forfeited` writer, and `RestoreClubCredit` (4.2) the `redeemed → active` order-cancellation restore writer.
    $clubCreditWriters = [
        'IssueClubCredit',
        'ApplyClubCredit',
        'ForfeitClubCredit',
        'RestoreClubCredit',
    ];

    // ...and the GDPR right-to-erasure writer (change parties-anonymisation — task 3.2). `AnonymiseCustomer`
    // overwrites the Customer PII + every scoped Address's personal fields IN PLACE, stamps `anonymised_at`, redacts
    // the Customer's audit snapshots, and records the PII-free `CustomerAnonymised` erasure event (added to the Action
    // by task 3.4). It is ORTHOGONAL to the status FSM (writes NO `status`, records NO STATUS event —
    // BR-K-Customer-2; `CustomerAnonymised` is an erasure event, not a status one), so the supply-side chain above
    // records none of it. Task 3.4 added the event but NO new Action CLASS, so this whitelist is unchanged. Named
    // `Anonymise*` not `Create*`, so the Create-filter below treats it as a transition Action and it MUST be
    // whitelisted here. Task 5.1's read-only `ExportCustomerData` — also non-Create — joins this group when it lands.
    $anonymisationWriters = [
        'AnonymiseCustomer',
    ];

    // ...and the ONLY non-Create (transition) Actions are exactly those supply-side + compliance + Hold-registry +
    // demand-side activation + demand-side status + Club Credit-writer + anonymisation ones. With task 3.2 the
    // demand-side status set is complete; the only names that stay ABSENT are `ActivateAccount` (the Account is born
    // `active` — design L8) and the deferred seams `WaitingList`/segment/Hero-cap (no Action class) and
    // `LockOriginatingClub`/`SetOriginatingClub` (the Originating-Club lock lives inside `ApproveProfile`, never a
    // standalone Action). If a deferred-seam Action were added without declaring it here, it would appear in this set
    // and fail the assertion (the whitelist grew one slice at a time).
    $transitions = array_values(array_filter($actions, static fn (string $name): bool => ! str_starts_with($name, 'Create')));
    expect($transitions)->toEqualCanonicalizing([...$supplySideTransitions, ...$complianceTransitions, ...$holdTransitions, ...$demandSideTransitions, ...$demandSideStatusTransitions, ...$clubCreditWriters, ...$anonymisationWriters]);

    // Reflect the Events namespace the same way: the still-deferred demand-side lifecycle event types do not even
    // EXIST in this change — they are not recordable (the follow-on demand-side changes introduce them). This
    // complements the runtime "zero demand-side events" assertion above: not merely unrecorded, but un-recordable.
    // The three ACTIVATION events (`CustomerActivated` / `ProfileActivated` / `OriginatingClubLocked`) now ship with
    // parties-membership-activation, so they are removed from this absent-set (the runtime loop above still pins
    // that the supply-side chain records none of them). What stays asserted-absent is the remaining demand side:
    // `AccountActivated` (Account suspension slice), `ProfileApproved` (the audit-only proof — § 15.2 names no
    // approve/decline event) and `CustomerSegmentChanged` (the segments slice).
    $eventFiles = glob(app_path('Modules/Parties/Events/*.php')) ?: [];
    $events = array_map(static fn (string $file): string => basename($file, '.php'), $eventFiles);
    expect($events)->not->toBeEmpty();
    foreach (['AccountActivated', 'ProfileApproved', 'CustomerSegmentChanged'] as $demandSideEvent) {
        expect($events)->not->toContain($demandSideEvent);
    }
});
