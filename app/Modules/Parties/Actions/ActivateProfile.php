<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProfileActivated;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a Profile membership `approved → active` and records its {@see ProfileActivated} event atomically
 * (parties-membership-activation, design L4/L5/L7/L8; party-registry — Requirements: Profile Activation,
 * Demand-Side Activation Events).
 *
 * This Action is the SOLE writer of `Profile.state` for the activation transition and the SINGLE writer of the
 * {@see ProfileActivated} event. Activation is reachable only from `approved` (the demand-side Profile FSM is
 * `applied → approved | rejected → active`, § 4.2.1); approval/decline are the audit-only step before it
 * ({@see ApproveProfile} / {@see DeclineProfile} record no Profile event — § 15.2 names none). Unlike the approve
 * path, activation HAS a § 15.2 event, so — like {@see ActivateProducer} — it injects the recorder + actor and
 * records `ProfileActivated` in the same transaction as the `state` write.
 *
 * MEMBERSHIP-FEE TRIGGER — DEFERRED MODULE-E SEAM (design L5; § 4.2.1 / § 15.2 / § 15.8): in production the
 * `approved → active` transition is driven by Module E's membership-fee-paid signal (or a free-club activation
 * where no fee applies — § 4.2.1). Module E does not exist, so the listener that would invoke this Action on that
 * signal is a documented Module-E seam — NO Module-E event contract is fabricated (zero-invention; § 15.2: "Module
 * K consumes Module E's `MembershipFeePaid` to drive this"). `ActivateProfile` ships as the within-module writer of
 * the transition, invoked by the free-club / operator path now and directly in tests — exactly as
 * {@see ActivateProducer} ships the transition with its upstream KYC gate as a seam. Club Credit (also fee-paid-
 * coupled) is an independent downstream consumer (`club-credit`); `ProfileActivated` provisions no credit inline,
 * so the split across slices breaks no ordering.
 *
 * HERO PACKAGE CAPACITY GATE — DEFERRED MODULE-A SEAM (design L7; § 13.1 / AC-K-J-13 / AC-K-XM-18): § 13.1 mandates
 * the membership no-oversell ceiling at "every Profile transition into `active`", but § 13.2 puts the cap on Module
 * A's Hero-Package Allocation `qty` — and Module A is unbuilt, so the gate cannot read it without inventing A's
 * contract. Activation therefore ships UNCAPPED, the same deferred-seam framing as {@see ApproveProfile} (the cap
 * also gates approval) and the {@see ActivateProducer} / {@see CloseClub} precedents. The cap (and the
 * `Applied → WaitingList` capacity-exceeded path) lands with `parties-hero-package` after Module A.
 *
 * From-state guarded and race-safe (design L4, mirroring `ActivateProducer`): inside ONE {@see DB::transaction} it
 * re-reads the Profile `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the from-state
 * assert carries correctness either way), asserts `state === approved`, then writes `active` and records the event.
 * A call on a Profile not in `approved` throws {@see IllegalProfileTransition::cannotActivate()} BEFORE any write,
 * and the transaction rolls back leaving the Profile and the event log unchanged. The payload reflects the
 * POST-transition `state`. `version` is NOT bumped (parties-core identity-revision semantics; the immutable domain
 * event is the audit record of the transition). The Model stays persistence-only; this Action is the sole state
 * writer. `ProfileActivated` is a ROOT event — the transition records no parent in its transaction, so no
 * causation/correlation is threaded. The actor is resolved from the {@see ActorContext} seam (System until real
 * principals wire in).
 */
class ActivateProfile
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(int $profileId): Profile
    {
        return DB::transaction(function () use ($profileId): Profile {
            // Transaction-locked re-read so two concurrent activation attempts serialize on PostgreSQL; the
            // from-state assert below is the correctness guarantee (the lock is a no-op on SQLite).
            $profile = Profile::query()->whereKey($profileId)->lockForUpdate()->firstOrFail();

            // Activation is reachable only from `approved` (§ 4.2.1); every other state rejects.
            if ($profile->state !== ProfileState::Approved) {
                throw IllegalProfileTransition::cannotActivate($profile->state);
            }

            $profile->update(['state' => ProfileState::Active]);

            // No causation/correlation passed → the recorder makes this a root event (its `correlation_id` defaults
            // to its own `event_id`): the activation records no parent event. The event class is the single source
            // of truth for the name / entity type / PII-free payload.
            $this->recorder->record(
                name: ProfileActivated::NAME,
                module: Module::Parties->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: ProfileActivated::ENTITY_TYPE,
                entityId: (string) $profile->id,
                payload: ProfileActivated::payload($profile),
            );

            return $profile;
        });
    }
}
