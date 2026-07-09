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
 * `applied → approved | rejected → active`, § 4.2.1); the approval and decline *writes* are audit-only
 * ({@see ApproveProfile} / {@see DeclineProfile} record no Profile event — § 15.2 names none). Per canon
 * MVP-DEC-016 `Approved` is a TRANSIENT pass-through: {@see ApproveProfile} drives straight through this activation
 * in its own transaction (approval = charge = activation — see TWO INVOCATION MODES below). Unlike the approve
 * write, activation HAS a § 15.2 event, so — like {@see ActivateProducer} — it injects the recorder + actor and
 * records `ProfileActivated` in the same transaction as the `state` write.
 *
 * TWO INVOCATION MODES (canon MVP-DEC-016; parties-membership-charge-on-approval — § 4.2.1): `ActivateProfile` is
 * the within-module writer of `approved → active`, invoked (a) SYNCHRONOUSLY by {@see ApproveProfile} inside the
 * approval transaction — the K-internal atomic activate-on-approval that keeps `Approved` transient (approval =
 * charge = activation in one operation) — plus the operator / test path today; and (b), when Module S lands, by the
 * deferred Module-S `MembershipFeePaid` listener (below).
 *
 * MEMBERSHIP-FEE TRIGGER — DEFERRED MODULE-S SEAM, RE-HOMED E → S (canon MVP-DEC-016 / DEC-173 / DEC-157; § 4.2.1 /
 * § 15.2 / § 15.8): in production the `approved → active` transition is driven by the membership-fee-paid signal (or
 * a free-club activation where no fee applies — § 4.2.1). Per canon this signal is re-homed to Module S: **Module S
 * emits** `MembershipFeePaid`, **Module E records** it, **Module K consumes** it (DEC-173), on payment-provider-
 * confirmed capture of the Hero-Package fee — firing **INV1; there is NO INV0** (DEC-157). This corrects the frozen
 * spec's stale "Module E emits for an INV0 charge" framing. No `MembershipFeePaid` event class exists — Module K
 * only *consumes* it — so the re-home is docblock/seam-name only, ZERO behaviour change (the RM-10 / DEC-018
 * precedent), and NO Module-S / Module-E event contract is fabricated (zero-invention; § 15.2: "Module K consumes
 * `MembershipFeePaid` to drive this"). The listener that would invoke this Action on that signal remains the
 * deferred Module-S seam — exactly as {@see ActivateProducer} ships the transition with its upstream KYC gate as a
 * seam. Club Credit (also fee-paid-coupled) is BUILT as an independent Module K entity (`club-credit`) with
 * `IssueClubCredit` as its writer; `ProfileActivated` still provisions no credit inline (the production issuance
 * trigger is that same Module-S `MembershipFeePaid` listener — the same deferred Phase-6 seam), so the split across
 * slices breaks no ordering.
 *
 * THIS ACTION IS DELIBERATELY NOT CAPACITY-GATED, AND THAT IS A DECISION — NOT AN OMISSION (parties-hero-package
 * design D4; § 13 / AC-K-J-13; canon MVP-DEC-017). The Hero-Package seat gate ships, but it lives on the
 * seat-CONSUMING caller and never here. `Approved` is a TRANSIENT pass-through (MVP-DEC-016, above), so
 * `approved → active` never NEWLY consumes a seat: the seat was already decided one step earlier, in this very
 * transaction, by {@see ApproveProfile} under the `parties_clubs` row lock. A gate here would re-decide that same
 * seat — and the moment `approved` were ever counted as seat-occupying, the second gate would refuse the very
 * approval that had just reserved the seat for it. The K-internal seat ledger (`ClubSeatOccupancy`, whose seat set
 * is `active` + `suspended`) excludes `approved` for exactly this reason. Its converse also holds: if any future
 * Action ever lets a Profile REST durably in `approved`, the seat set is wrong — not this non-gate.
 *
 * WHEN THE DEFERRED MODULE-S `MembershipFeePaid` LISTENER LANDS (above), IT — not this Action — CARRIES THE GATE.
 * That listener drives `approved → active` on a payment confirmation that may arrive long after the approval, so it
 * is a seat-consuming entry point in its own right: it must take the Club-row lock and evaluate capacity at its own
 * boundary, exactly as {@see ApproveProfile} does today. Pushing the gate down into this Action instead would
 * double-gate the approval path to buy that listener nothing.
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
