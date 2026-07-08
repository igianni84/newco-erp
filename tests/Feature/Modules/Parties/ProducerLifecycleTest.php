<?php

use App\Modules\Parties\Actions\ActivateProducer;
use App\Modules\Parties\Actions\CreateProducer;
use App\Modules\Parties\Actions\RetireProducer;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ClubSunset;
use App\Modules\Parties\Events\ProducerActivated;
use App\Modules\Parties\Events\ProducerRetired;
use App\Modules\Parties\Exceptions\IllegalProducerTransition;
use App\Modules\Parties\Exceptions\SeparationOfDutiesViolation;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Producer;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorContext;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the Producer supply-side lifecycle (parties-producer-lifecycle; design L1/L2/L4/L5/L6/L8; party-registry —
 * Requirements: Producer Lifecycle, Supply-Side Lifecycle Events). It covers three things: (1) the structural
 * read the retirement cascade walks — the within-module {@see Producer::clubs()} `hasMany` (design L6), the
 * inverse of {@see Club::producer()}, scoped to the Producer's operated Clubs and boundary-clean (both entities
 * are Module K); (2) the `draft → active` transition via {@see ActivateProducer} — the SOLE writer of
 * `Producer.status` for activation and the SINGLE writer of {@see ProducerActivated}, always a root event
 * (activation is never a cascade target); and (3) the `active → retired` transition via {@see RetireProducer},
 * which records {@see ProducerRetired} (the cascade ROOT) and CASCADES sunset onto every operated Club still
 * `active`, each recording a {@see ClubSunset} caused by — and sharing the correlation of — the retirement
 * (design L5/L6). The KYC-cleared gate on activation (Module K PRD § 4.4 / BR-K-Producer-2) is enforced here
 * (parties-compliance, design L5): activation requires `kyc_status` cleared — `verified`, `not_required`, or
 * NULL (treated as cleared for additivity) — and is rejected for `pending`/`rejected`, leaving the Producer
 * `draft`. The Profile leg of the § 10.2 retirement cascade — cancelling every `Active`/`Lapsed` Profile under
 * each sunsetting Club with a Producer-initiated reason, AUDIT-ONLY (no `ProfileCancelled` event) — is now
 * performed by {@see RetireProducer} and pinned here (parties-module-k-br-guards RM-19).
 *
 * RefreshDatabase per the task hint; the transition opens its OWN DB::transaction, so the recorder's
 * `transactionLevel() === 0` guard is satisfied by the savepoint under the wrapper (the event being recorded at
 * all is itself proof of the in-transaction wiring). The relation and the status write + event envelope are
 * exercised against a real schema so the `producer_id` FK scope and the causation/correlation columns round-trip
 * on both engines (SQLite here; PostgreSQL 17 in the cross-engine close — knowledge/testing).
 */
uses(RefreshDatabase::class);

/**
 * Create a draft Producer through the real {@see CreateProducer} Action as operator $creatorId, so its
 * ProducerCreated event carries that actor_id — the creator lineage the separation-of-duties floor recovers
 * from `domain_events` (change parties-producer-approval-sod, design D3). Named distinctly from the sibling
 * ProducerApprovalGovernanceTest's `producerSodDraft` (the one shared Pest function namespace forbids a redeclare).
 */
function producerCreatedByOperator(int $creatorId): Producer
{
    return app(ActorContext::class)->runAs(ActorRole::NewcoOps, $creatorId, fn (): Producer => app(CreateProducer::class)->handle(
        name: 'Domaine Leflaive',
        region: 'Burgundy',
        country: 'FR',
    ));
}

/** Activate $producerId as operator $approverId — the distinct approver that clears the SoD operator/distinctness floor. */
function activateProducerAsOperator(int $producerId, int $approverId): Producer
{
    return app(ActorContext::class)->runAs(ActorRole::NewcoOps, $approverId, fn (): Producer => app(ActivateProducer::class)->handle($producerId));
}

it('exposes the operated Clubs through the within-module clubs() hasMany', function () {
    $producer = Producer::factory()->create();
    Club::factory()->count(3)->create(['producer_id' => $producer->id]);

    // The relation query counts the Producer's Clubs (the read RetireProducer walks — design L6).
    expect($producer->clubs()->count())->toBe(3);

    // The lazy-loaded dynamic property hydrates an Eloquent Collection of Club models.
    expect($producer->clubs)->toBeInstanceOf(Collection::class)
        ->and($producer->clubs)->toHaveCount(3);
    expect($producer->clubs)->each->toBeInstanceOf(Club::class);
});

it('returns an empty collection for a Producer that operates no Clubs', function () {
    $producer = Producer::factory()->create();

    expect($producer->clubs()->count())->toBe(0)
        ->and($producer->clubs)->toBeEmpty();
});

it('scopes clubs() to the owning Producer — Clubs of a different Producer are excluded', function () {
    $producer = Producer::factory()->create();
    $other = Producer::factory()->create();

    Club::factory()->count(2)->create(['producer_id' => $producer->id]);
    Club::factory()->count(3)->create(['producer_id' => $other->id]);

    // The hasMany is keyed on producer_id: each Producer sees only its own Clubs (the cascade must never
    // reach across Producers).
    expect($producer->clubs()->count())->toBe(2)
        ->and($other->clubs()->count())->toBe(3)
        ->and($producer->clubs->pluck('producer_id')->unique()->values()->all())->toBe([$producer->id]);
});

it('activates a draft Producer and records a ProducerActivated in the same transaction, tagged parties and PII-free', function () {
    // Created by operator 101 through the real CreateProducer (recording its ProducerCreated lineage), then
    // activated by a DISTINCT operator 202 — the separation-of-duties floor (change parties-producer-approval-sod)
    // requires an authenticated operator distinct from the creator.
    $producer = producerCreatedByOperator(101);   // born `draft`, creator lineage 101

    $returned = activateProducerAsOperator($producer->id, 202);

    // The action returns the transitioned model, and the persisted row re-hydrates to `active`.
    expect($returned->status)->toBe(ProducerStatus::Active)
        ->and(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Active);

    // Exactly one ProducerActivated — the real CreateProducer also recorded a ProducerCreated, so the total is
    // two events, but the transition under test contributes exactly the one ProducerActivated.
    expect(DomainEvent::query()->where('name', ProducerActivated::NAME)->count())->toBe(1);

    $event = DomainEvent::query()->where('name', ProducerActivated::NAME)->sole();

    expect($event->module)->toBe('parties')                     // Module::Parties->value
        ->and($event->entity_type)->toBe('Producer')
        ->and($event->entity_id)->toBe((string) $producer->id)  // envelope entity_id is a string
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)    // the approving operator (the SoD floor)
        ->and($event->actor_id)->toEqual(202);                  // uncast bigint — loose compare spans engines

    // Payload asserted BY KEY (knowledge/testing trap 3 — never byte-compare PG jsonb): the Producer by id and
    // the POST-transition status, and nothing more — the exact key set is pinned so the PII-free contract
    // cannot silently widen.
    expect(array_keys($event->payload))->toEqualCanonicalizing(['producer_id', 'status']);

    expect($event->payload['producer_id'])->toBe($producer->id)
        ->and($event->payload['status'])->toBe('active');

    // PII-free / transition-shaped: a Producer is not a Party (§ 4.4) and carries no personal data anyway, and
    // the structural creation fields are not the subject of a transition event — they are deliberately absent.
    expect($event->payload)->not->toHaveKey('name')
        ->and($event->payload)->not->toHaveKey('region')
        ->and($event->payload)->not->toHaveKey('country');

    // A standalone activation is a ROOT event: the recorder defaults `correlation_id` to the event's own
    // `event_id` and leaves `causation_id` null (activation is never a cascade target).
    expect($event->causation_id)->toBeNull()
        ->and($event->correlation_id)->toBe($event->event_id);
});

it('activates a draft Producer whose KYC is cleared (verified, not_required, or NULL), recording a ProducerActivated', function (?KycStatus $kyc) {
    // AC-K-FSM-7 positive arm (parties-compliance, design L5; § 4.4 / BR-K-Producer-2): the cleared KYC
    // states — `verified`, `not_required`, and a NULL `kyc_status` (never touched, treated as cleared for
    // additivity, ADR 2026-06-17) — all admit activation. Created by operator 101 and activated by a DISTINCT
    // operator 202 (the SoD floor); the KYC value is set on the created row (CreateProducer takes no kyc_status —
    // a persistence-only update, no event), leaving the ProducerCreated lineage intact. The gate runs after the
    // `draft` from-state assert and the SoD floor.
    $producer = producerCreatedByOperator(101);
    $producer->update(['kyc_status' => $kyc]);   // NULL | not_required | verified — cleared, no event

    $returned = activateProducerAsOperator($producer->id, 202);

    expect($returned->status)->toBe(ProducerStatus::Active)
        ->and(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Active)
        ->and(DomainEvent::query()->where('name', ProducerActivated::NAME)->count())->toBe(1);
})->with([
    'NULL kyc_status (never touched)' => [null],
    'not_required' => [KycStatus::NotRequired],
    'verified' => [KycStatus::Verified],
]);

it('rejects activating a draft Producer whose KYC is not cleared (pending or rejected), leaving it draft with no event', function (KycStatus $kyc) {
    // AC-K-FSM-7 negative arm: the blocking KYC states reject activation. Activated by an operator (202) distinct
    // from the creator (101), so the SoD floor PASSES and the KYC-cleared gate is the sole reason for the throw —
    // its message names KYC, distinguishing it from both the from-state guard and the SoD floor. The transaction
    // rolls back: status stays `draft` and no ProducerActivated is recorded.
    $producer = producerCreatedByOperator(101);
    $producer->update(['kyc_status' => $kyc]);   // pending | rejected — blocking, no event

    expect(fn () => activateProducerAsOperator($producer->id, 202))
        ->toThrow(IllegalProducerTransition::class, 'KYC');

    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Draft)
        ->and(DomainEvent::query()->where('name', ProducerActivated::NAME)->count())->toBe(0);
})->with([
    'pending' => [KycStatus::Pending],
    'rejected' => [KycStatus::Rejected],
]);

it('still activates a Producer created before parties-compliance — NULL kyc_status is cleared (additive regression)', function () {
    // The additive-safety regression (design L5): a Producer with NULL `kyc_status` (the nullable column has no
    // default and no backfill — DEC-071) is treated as cleared at the gate, so it keeps activating. Created by
    // operator 101 (CreateProducer sets no kyc_status → NULL) and activated by a distinct operator 202 (the SoD
    // floor) — tightening the gate must never break shipped rows.
    $producer = producerCreatedByOperator(101);   // born `draft`; CreateProducer sets no kyc_status → NULL
    expect($producer->kyc_status)->toBeNull();

    $returned = activateProducerAsOperator($producer->id, 202);

    expect($returned->status)->toBe(ProducerStatus::Active)
        ->and(DomainEvent::query()->where('name', ProducerActivated::NAME)->count())->toBe(1);
});

it('rejects a creator self-approval on the separation-of-duties floor, leaving the Producer draft with no event', function () {
    // design D1/D3 — the approver SHALL differ from the creator. Operator 101 creates the Producer and then attempts
    // to activate it itself → the distinct-actor floor is breached, so the guard throws `creator_may_not_approve`
    // before any write. The Producer stays `draft` and no ProducerActivated is recorded (the transaction rolls back).
    $producer = producerCreatedByOperator(101);

    expect(fn () => activateProducerAsOperator($producer->id, 101))
        ->toThrow(SeparationOfDutiesViolation::class, (string) __('parties.approval.creator_may_not_approve', ['entity' => 'Producer']));

    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Draft)
        ->and(DomainEvent::query()->where('name', ProducerActivated::NAME)->count())->toBe(0);
});

it('rejects activation under the system/null actor on the operator-principal floor, leaving the Producer draft', function () {
    // design D4 (CLAUDE.md invariant 8): activation requires an authenticated operator. The default System actor
    // (no runAs, no operator guard) cannot satisfy a distinct-actor floor and is rejected on the operator-principal
    // leg — closing the "System actor accepted" hole. A genuine creator lineage (101) exists, but the principal
    // check fires FIRST, so the violation is `requires_operator_principal`, not `creator_may_not_approve`.
    $producer = producerCreatedByOperator(101);

    expect(fn () => app(ActivateProducer::class)->handle($producer->id))
        ->toThrow(SeparationOfDutiesViolation::class, (string) __('parties.approval.requires_operator_principal', ['entity' => 'Producer']));

    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Draft)
        ->and(DomainEvent::query()->where('name', ProducerActivated::NAME)->count())->toBe(0);
});

it('activates under a distinct operator and records the ProducerActivated with the approver as actor', function () {
    // design D1 — the 2-step Creator → Approver happy path (AC-K-J-10 at the configured depth): operator 101 creates,
    // a distinct operator 202 activates. The floor is satisfied, the Producer reaches `active`, and the recorded
    // ProducerActivated carries operator 202 (the approver) as its actor.
    $producer = producerCreatedByOperator(101);

    activateProducerAsOperator($producer->id, 202);

    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Active);

    $event = DomainEvent::query()->where('name', ProducerActivated::NAME)->sole();
    expect($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual(202);   // the approver — uncast bigint, loose compare spans engines
});

it('rejects a self-approval on a KYC-pending Producer on the SoD floor, before the KYC gate', function () {
    // design D6 order (from-state → operator-principal → distinct-actor → KYC → write): a self-approval is rejected
    // on the SoD floor even when KYC is NOT cleared. Operator 101 creates the Producer, its KYC is set `pending`
    // (blocking), and 101 self-approves → the guard throws `creator_may_not_approve` (SoD), NOT the KYC violation,
    // proving SoD precedes KYC. Nothing is written.
    $producer = producerCreatedByOperator(101);
    $producer->update(['kyc_status' => KycStatus::Pending]);   // blocking KYC — but SoD is evaluated first

    expect(fn () => activateProducerAsOperator($producer->id, 101))
        ->toThrow(SeparationOfDutiesViolation::class, (string) __('parties.approval.creator_may_not_approve', ['entity' => 'Producer']));

    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Draft)
        ->and(DomainEvent::query()->where('name', ProducerActivated::NAME)->count())->toBe(0);
});

it('rejects activating a Producer already in active and records nothing', function () {
    $producer = Producer::factory()->create(['status' => ProducerStatus::Active]);

    expect(fn () => app(ActivateProducer::class)->handle($producer->id))
        ->toThrow(IllegalProducerTransition::class);

    // The from-state guard fires before any write and the transaction rolls back: the status is unchanged and
    // no ProducerActivated event was recorded.
    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Active)
        ->and(DomainEvent::query()->where('name', ProducerActivated::NAME)->count())->toBe(0);
});

it('rejects activating a Producer already in retired and records nothing', function () {
    $producer = Producer::factory()->create(['status' => ProducerStatus::Retired]);

    expect(fn () => app(ActivateProducer::class)->handle($producer->id))
        ->toThrow(IllegalProducerTransition::class);

    // `retired` is terminal in the linear FSM — activation cannot resurrect it; the guard leaves the row
    // untouched and records nothing.
    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Retired)
        ->and(DomainEvent::query()->where('name', ProducerActivated::NAME)->count())->toBe(0);
});

it('retires an active Producer and cascades sunset onto its active Clubs, each ClubSunset caused by the retirement', function () {
    // The § 10.2 offboarding cascade (Producer → Club leg) — the spec scenario "Retire an active Producer
    // cascades Club sunset": GIVEN an active Producer operating two active Clubs and one already-closed Club.
    $producer = Producer::factory()->create(['status' => ProducerStatus::Active]);
    $activeA = Club::factory()->create(['producer_id' => $producer->id]);   // born active
    $activeB = Club::factory()->create(['producer_id' => $producer->id]);   // born active
    $closed = Club::factory()->create(['producer_id' => $producer->id, 'status' => ClubStatus::Closed]);

    $returned = app(RetireProducer::class)->handle($producer->id);

    // The Producer transitions to `retired` (returned model + the persisted row).
    expect($returned->status)->toBe(ProducerStatus::Retired)
        ->and(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Retired);

    // The two active Clubs are sunset; the already-closed Club is left UNCHANGED — the cascade only touches
    // active Clubs (it is idempotent over already-transitioned ones).
    expect(Club::findOrFail($activeA->id)->status)->toBe(ClubStatus::Sunset)
        ->and(Club::findOrFail($activeB->id)->status)->toBe(ClubStatus::Sunset)
        ->and(Club::findOrFail($closed->id)->status)->toBe(ClubStatus::Closed);

    // Exactly three events recorded in the one transaction — one ProducerRetired root + two cascade ClubSunset,
    // and nothing for the untouched closed Club (no ClubClosed, no demand-side event).
    expect(DomainEvent::query()->count())->toBe(3);

    $retired = DomainEvent::query()->where('name', ProducerRetired::NAME)->sole();

    expect($retired->module)->toBe('parties')                     // Module::Parties->value
        ->and($retired->entity_type)->toBe('Producer')
        ->and($retired->entity_id)->toBe((string) $producer->id)  // envelope entity_id is a string
        ->and($retired->actor_role)->toBe(ActorRole::System);     // the ActorContext seam default

    // ProducerRetired payload asserted BY KEY (knowledge/testing trap 3): the two-key PII-free shape with the
    // POST-transition status, pinned so the contract cannot silently widen.
    expect(array_keys($retired->payload))->toEqualCanonicalizing(['producer_id', 'status']);
    expect($retired->payload['producer_id'])->toBe($producer->id)
        ->and($retired->payload['status'])->toBe('retired');

    // The retirement is the ROOT of the cascade: it carries no cause and is self-correlated.
    expect($retired->causation_id)->toBeNull()
        ->and($retired->correlation_id)->toBe($retired->event_id);

    // One cascade ClubSunset per active Club (none for the closed Club), addressed at the two active Clubs.
    $sunsets = DomainEvent::query()->where('name', ClubSunset::NAME)->get();
    expect($sunsets)->toHaveCount(2)
        ->and($sunsets->pluck('entity_id')->all())
        ->toEqualCanonicalizing([(string) $activeA->id, (string) $activeB->id]);

    // Cascade causal linkage (design L5; spec "Cascade events are causally linked to the retirement"): every
    // cascade ClubSunset carries the ProducerRetired event's `id` as `causation_id` and shares its
    // `correlation_id` — the offboarding is one queryable thread in the audit log.
    foreach ($sunsets as $sunset) {
        expect($sunset->causation_id)->toBe($retired->id)
            ->and($sunset->correlation_id)->toBe($retired->correlation_id);
    }
});

it('rejects retiring a Producer not in active, rolls back, and cascades nothing', function () {
    // A `draft` Producer is not retirable (retirement is reachable only from `active`); an operated active Club
    // is present to prove the cascade does NOT run when the from-state guard rejects the call.
    $producer = Producer::factory()->create(['status' => ProducerStatus::Draft]);
    $club = Club::factory()->create(['producer_id' => $producer->id]);   // born active

    expect(fn () => app(RetireProducer::class)->handle($producer->id))
        ->toThrow(IllegalProducerTransition::class);

    // The guard fires before any write and the whole transaction rolls back together: the Producer is unchanged,
    // the operated Club is NOT sunset, and neither ProducerRetired nor any cascade ClubSunset was recorded.
    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Draft)
        ->and(Club::findOrFail($club->id)->status)->toBe(ClubStatus::Active)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('rejects retiring a Producer already in retired and records nothing', function () {
    $producer = Producer::factory()->create(['status' => ProducerStatus::Retired]);

    expect(fn () => app(RetireProducer::class)->handle($producer->id))
        ->toThrow(IllegalProducerTransition::class);

    // `retired` is terminal in the linear FSM — re-retiring is rejected by the same `=== active` guard, leaving
    // the row untouched and recording nothing.
    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Retired)
        ->and(DomainEvent::query()->where('name', ProducerRetired::NAME)->count())->toBe(0);
});

it('retires an active Producer that operates no active Clubs, recording only the root ProducerRetired', function () {
    // The cascade no-ops cleanly when there is nothing `active` to sunset: only a closed Club exists, so the
    // walk over the active-Club set is empty and a single root ProducerRetired is recorded.
    $producer = Producer::factory()->create(['status' => ProducerStatus::Active]);
    Club::factory()->create(['producer_id' => $producer->id, 'status' => ClubStatus::Closed]);

    $returned = app(RetireProducer::class)->handle($producer->id);

    expect($returned->status)->toBe(ProducerStatus::Retired);

    // Exactly one event — the root ProducerRetired — and zero cascade ClubSunset (no active Club to sunset).
    expect(DomainEvent::query()->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ProducerRetired::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ClubSunset::NAME)->count())->toBe(0);
});

it('cascades the Profile leg on retirement — cancels every Active/Lapsed Profile under a sunsetting Club with a Producer-initiated reason, audit-only', function () {
    // The § 10.2 offboarding Profile leg (RM-19 / AC-K-J-19 / AC-K-EVT-20): GIVEN an active Producer operating two
    // active Clubs, each holding memberships in the two cancellable from-states (`Active` and `Lapsed`). The
    // Profiles are pure factory fixtures (they bypass CreateProfile, so no ProfileCreated event — the only events
    // in play are the retirement's own).
    $producer = Producer::factory()->create(['status' => ProducerStatus::Active]);
    $clubA = Club::factory()->create(['producer_id' => $producer->id]);   // born active
    $clubB = Club::factory()->create(['producer_id' => $producer->id]);   // born active

    $activeUnderA = Profile::factory()->create(['club_id' => $clubA->id, 'state' => ProfileState::Active]);
    $lapsedUnderA = Profile::factory()->create(['club_id' => $clubA->id, 'state' => ProfileState::Lapsed]);
    $activeUnderB = Profile::factory()->create(['club_id' => $clubB->id, 'state' => ProfileState::Active]);

    app(RetireProducer::class)->handle($producer->id);

    // Both operated Clubs sunset (the parents); every Active/Lapsed Profile under them is Cancelled (the children)
    // carrying the Producer-initiated offboarding reason — the leg ran after the ClubSunset in the one atomic
    // transaction (parent-before-child).
    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Retired)
        ->and(Club::findOrFail($clubA->id)->status)->toBe(ClubStatus::Sunset)
        ->and(Club::findOrFail($clubB->id)->status)->toBe(ClubStatus::Sunset);

    foreach ([$activeUnderA, $lapsedUnderA, $activeUnderB] as $profile) {
        $persisted = Profile::findOrFail($profile->id);
        expect($persisted->state)->toBe(ProfileState::Cancelled)
            ->and($persisted->cancellation_reason)->toBe(RetireProducer::OFFBOARDING_CANCELLATION_REASON);
    }

    // AUDIT-ONLY (design D1): the three per-Profile cancellations record NO domain event — the § 15.2 family names
    // no `ProfileCancelled`. Exactly three events: the ProducerRetired root + the two cascade ClubSunset, and
    // nothing for the cancellations (nor any event carrying a Profile entity_type).
    expect(DomainEvent::query()->where('name', 'ProfileCancelled')->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(3)
        ->and(DomainEvent::query()->where('name', ProducerRetired::NAME)->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', ClubSunset::NAME)->count())->toBe(2)
        ->and(DomainEvent::query()->where('entity_type', 'Profile')->count())->toBe(0);
});

it('leaves Profiles in non-Active/Lapsed states, and Profiles under a non-sunsetting Club, untouched by the offboarding cascade', function () {
    // The leg is precisely scoped: it touches ONLY Active/Lapsed Profiles under a Club that SUNSETS in this
    // cascade. Everything else is left to its own lifecycle.
    $producer = Producer::factory()->create(['status' => ProducerStatus::Active]);
    $sunsetting = Club::factory()->create(['producer_id' => $producer->id]);                                       // born active → sunset
    $alreadyClosed = Club::factory()->create(['producer_id' => $producer->id, 'status' => ClubStatus::Closed]);    // NOT sunsetting now

    // Under the sunsetting Club: two non-cancellable-from-state Profiles + one already-terminal Profile — all must
    // survive unchanged. The from-state filter excludes them; had the walk instead handed a terminal Profile to
    // CancelProfile its own guard would throw and roll the whole retirement back, so their survival also proves
    // the filter is applied at the query, not merely tolerated downstream.
    $applied = Profile::factory()->create(['club_id' => $sunsetting->id, 'state' => ProfileState::Applied]);
    $suspended = Profile::factory()->create(['club_id' => $sunsetting->id, 'state' => ProfileState::Suspended]);
    $alreadyCancelled = Profile::factory()->create([
        'club_id' => $sunsetting->id, 'state' => ProfileState::Cancelled, 'cancellation_reason' => 'voluntary',
    ]);

    // Under the already-closed Club (out of the sunsetting set): an Active Profile the cascade must NOT reach.
    $activeUnderClosed = Profile::factory()->create(['club_id' => $alreadyClosed->id, 'state' => ProfileState::Active]);

    app(RetireProducer::class)->handle($producer->id);

    // The cascade genuinely ran (non-vacuous): the Producer is retired and the operated Club sunset.
    expect(Producer::findOrFail($producer->id)->status)->toBe(ProducerStatus::Retired)
        ->and(Club::findOrFail($sunsetting->id)->status)->toBe(ClubStatus::Sunset)
        ->and(Club::findOrFail($alreadyClosed->id)->status)->toBe(ClubStatus::Closed);   // idempotent over already-transitioned Clubs

    // Non-Active/Lapsed Profiles under the sunsetting Club are untouched; the already-Cancelled one keeps its
    // ORIGINAL reason (not re-stamped with the offboarding token — it was never re-cancelled).
    expect(Profile::findOrFail($applied->id)->state)->toBe(ProfileState::Applied)
        ->and(Profile::findOrFail($suspended->id)->state)->toBe(ProfileState::Suspended)
        ->and(Profile::findOrFail($alreadyCancelled->id)->state)->toBe(ProfileState::Cancelled)
        ->and(Profile::findOrFail($alreadyCancelled->id)->cancellation_reason)->toBe('voluntary');

    // The Active Profile under the already-closed Club is out of scope (its Club is not sunsetting) — still Active.
    expect(Profile::findOrFail($activeUnderClosed->id)->state)->toBe(ProfileState::Active)
        ->and(Profile::findOrFail($activeUnderClosed->id)->cancellation_reason)->toBeNull();

    // Still audit-only: no `ProfileCancelled` slipped in, and only the retirement + its single cascade sunset were
    // recorded (the non-cancellable Profiles produced no cancellation call at all).
    expect(DomainEvent::query()->where('name', 'ProfileCancelled')->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(2);
});
