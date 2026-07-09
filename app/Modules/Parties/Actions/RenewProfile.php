<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Contracts\HeroPackageCapacityReader;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\ProfileRenewed;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use App\Modules\Parties\Models\Profile;
use App\Modules\Parties\Support\ClubSeatOccupancy;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a Profile membership `Lapsed → Active` within the 30-day grace window, clears the `lapsed_at` anchor,
 * and records its {@see ProfileRenewed} event atomically (parties-membership-suspension, design L3/L4/L5/L9/L10/L11;
 * parties-hero-package, design D8/D9; party-registry — Requirements: Profile Lapse and Grace Renewal, Hero Package
 * Capacity Invariant, Demand-Side Status Events). It is the inverse of
 * {@see LapseProfile}.
 *
 * NAMING TRAP (design L3): the grace restore (`lapsed → active`) records {@see ProfileRenewed}, NOT
 * `ProfileReactivated` (which is the `suspended → active` edge only — {@see ReactivateProfile}). Never conflate the
 * lapse-renewal cycle with suspension restore.
 *
 * THE SECOND NAMING TRAP, AND THE ONE THAT BREAKS THE INVARIANT (parties-hero-package design D9). THIS Action is the
 * 30-day grace RE-ACTIVATION, and it IS capacity-gated: a `lapsed` Profile freed its seat, so `lapsed → active`
 * RE-CONSUMES one (canon § 13.1 — *"a re-activation within the 30-day grace re-consumes a seat, subject to the cap at
 * re-activation time"*). The GRANDFATHERED renewal of canon MVP-DEC-011 / AC-K-J-15a — explicitly NOT cap-gated,
 * because its seat was never freed — is the period rollover of an ALREADY-`active` Profile into a new club year. That
 * rollover IS NOT MODELLED here: `parties_profiles` carries no `valid_to`, no period column and no rollover Action.
 * Same word, opposite rule; never "grandfather" this Action.
 *
 * THE 30-DAY GRACE IS ENFORCED IN CODE (design L5; DEC-034; § 4.2.1): renewal is permitted ONLY when
 * `state === lapsed` AND the current moment is within 30 days of `lapsed_at` (the anchor {@see LapseProfile} stamps).
 * Past the grace window the call is rejected — in production the deferred scheduler instead transitions the Profile
 * `lapsed → cancelled` (the `CancelProfile` Action — task 2.3). Both a wrong from-state AND a past-grace lapsed
 * Profile throw {@see IllegalProfileTransition::cannotRenew()}; the boundary is INCLUSIVE (renewal exactly 30 days
 * after `lapsed_at` still succeeds).
 *
 * THE HERO-PACKAGE SEAT GATE, AND WHY IT RUNS LAST (parties-hero-package design D3/D8/D9; § 13 / AC-K-J-13; canon
 * MVP-DEC-017). Renewal is a seat-CONSUMING transition, so — inside this transaction and BEFORE any write —
 * {@see ClubSeatOccupancy::lockAndCountOccupiedSeats()} takes the `parties_clubs` row lock and only THEN counts the
 * seat-occupying (`active` + `suspended`) set: same-Club seat-consuming transactions queue on that one row, different
 * Clubs stay parallel. Locking the Profile row alone serialises nothing here (two concurrent renewals in one Club lock
 * two different Profile rows and both observe the same last free seat). The capacity itself is never stored in Module K
 * (`AC-K-XM-20`) — it is read through Module K's own {@see HeroPackageCapacityReader} port, whose launch adapter is
 * config-backed; an UNSET capacity means UNCAPPED, the shipped production posture, so the gate passes unconditionally
 * and every pre-existing caller behaves exactly as before (a dark launch).
 *
 * THE GATE ORDER IS LOAD-BEARING: from-state guard → GRACE sub-gate → Club-row lock → seat count → capacity gate. A
 * PAST-GRACE renewal therefore reports the GRACE reason regardless of capacity (the membership has expired; the seat
 * ledger is not the reason it cannot come back), and neither a wrong-state nor a past-grace call ever locks a Club row.
 *
 * AT PARITY THIS ACTION THROWS; IT NEVER DIVERTS (design D8). Unlike {@see ApproveProfile} — where an `applied`
 * Profile at parity has an edge to take and LANDS in `waiting_list` — canon draws NO `lapsed → waiting_list` edge
 * (§ 4.2.1:186). Inventing one would both fabricate a transition and DISCARD `lapsed_at`, burning the grace clock the
 * member is entitled to. So a within-grace renewal into a full Club raises
 * {@see IllegalProfileTransition::clubAtCapacity()} — naming the capacity and the occupancy the gate just decided on —
 * the Profile stays `lapsed` with its anchor intact, its grace keeps running, and the operator reads why.
 *
 * RENEWAL-PAYMENT TRIGGER — DEFERRED MODULE-E SEAM (design L5; § 4.2.1 / § 15.2 / § 15.8): in production the
 * `lapsed → active` transition is driven by a renewal payment (Module E's `MembershipFeePaid` signal extending
 * validity). Module E does not exist, so the listener that would invoke this Action on that signal is a documented
 * Module-E seam — NO Module-E event contract is fabricated (zero-invention). `RenewProfile` ships as the within-module
 * writer, invoked directly now.
 *
 * STATE-PRESERVING inverse of lapse (design L9): write ONLY `state` + clear the `lapsed_at` anchor. From-state guarded
 * and race-safe (design L4, mirroring {@see LapseProfile}): inside ONE {@see DB::transaction} it re-reads the Profile
 * `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the from-state + grace assert carries
 * correctness either way), asserts the grace guard, evaluates the seat gate, then writes `active` + clears `lapsed_at`
 * and records the event. A disallowed call throws BEFORE any write, and the transaction rolls back leaving the Profile
 * (state and `lapsed_at`) and the event log unchanged. The payload reflects the POST-transition `state`. `version` is
 * NOT bumped (parties-core identity-revision semantics; the immutable domain event is the audit record of the
 * transition). The Model stays persistence-only; this Action is the sole state writer. `ProfileRenewed` is a ROOT event
 * — a directly-invoked renewal records no parent in its transaction, so no causation/correlation is threaded. The
 * actor is resolved from the {@see ActorContext} seam (System until real principals wire in).
 */
class RenewProfile
{
    /**
     * The seat ledger AND the capacity port are both injected — exactly as in {@see ApproveProfile}, and for the same
     * reason: this Action builds the operator-facing capacity rejection, which names the capacity number, so it reads
     * the port directly for that one value. The ledger still owns the rule (`null` ⇒ uncapped; `>=`, not `>`); it is
     * never re-spelled here.
     */
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
        private readonly ClubSeatOccupancy $seats,
        private readonly HeroPackageCapacityReader $capacity,
    ) {}

    public function handle(int $profileId): Profile
    {
        return DB::transaction(function () use ($profileId): Profile {
            // Transaction-locked re-read so two concurrent attempts serialize on PostgreSQL; the from-state + grace
            // assert below is the correctness guarantee (the lock is a no-op on SQLite).
            $profile = Profile::query()->whereKey($profileId)->lockForUpdate()->firstOrFail();

            // Renewal is reachable only from `lapsed` AND only within the 30-day grace window of `lapsed_at`
            // (DEC-034 — the boundary is inclusive). A wrong from-state OR a past-grace lapsed Profile both reject;
            // past grace the deferred scheduler instead cancels the Profile (`CancelProfile`, task 2.3).
            //
            // BOTH SUB-GATES PRECEDE THE CAPACITY GATE BELOW (design D8/D9): a past-grace renewal reports the GRACE
            // reason whether or not the Club has a seat — the membership expired, and the seat ledger is not why it
            // cannot come back — and a doomed call takes no Club-row lock.
            $graceDeadline = $profile->lapsed_at?->addDays(30);
            if (
                $profile->state !== ProfileState::Lapsed
                || $graceDeadline === null
                || CarbonImmutable::now()->greaterThan($graceDeadline)
            ) {
                throw IllegalProfileTransition::cannotRenew($profile->state);
            }

            // THE SEAT GATE (design D3/D9). A `lapsed` Profile released its seat, so the grace re-activation
            // RE-CONSUMES one (canon § 13.1) — this is a seat-consuming transition and its occupancy must be counted
            // under the `parties_clubs` row lock, acquired STRICTLY FIRST. The Profile-row lock above serialises
            // nothing here: two concurrent renewals in one Club lock two different Profile rows and would both
            // observe the same last free seat.
            $occupiedSeats = $this->seats->lockAndCountOccupiedSeats($profile->club_id);
            $capacity = $this->capacity->forClub($profile->club_id);

            // `wouldOversell()` owns the rule — including "a `null` capacity is UNCAPPED, never an oversell" — so it
            // is not re-spelled here. The `!== null` conjunct is redundant to that rule and load-bearing to the TYPES:
            // `clubAtCapacity()` takes an `int` capacity precisely because an uncapped Club can never reach it.
            //
            // UNLIKE `ApproveProfile`, THIS ACTION DOES NOT DIVERT AT PARITY (design D8): canon draws no
            // `lapsed → waiting_list` edge, and writing one would clear `lapsed_at` and burn the member's grace clock.
            // The Profile stays `lapsed`, its anchor intact, and the transaction rolls back with no event recorded.
            if ($capacity !== null && $this->seats->wouldOversell($profile->club_id, $occupiedSeats)) {
                throw IllegalProfileTransition::clubAtCapacity($profile->state, $capacity, $occupiedSeats);
            }

            // State-preserving inverse of lapse (design L9): write ONLY `state` and clear the grace anchor.
            $profile->update([
                'state' => ProfileState::Active,
                'lapsed_at' => null,
            ]);

            // No causation/correlation passed → the recorder makes this a root event (its `correlation_id` defaults
            // to its own `event_id`): a directly-invoked renewal records no parent event. The event class is the
            // single source of truth for the name / entity type / PII-free payload (`ProfileRenewed`, not
            // `ProfileReactivated` — L3).
            $this->recorder->record(
                name: ProfileRenewed::NAME,
                module: Module::Parties->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: ProfileRenewed::ENTITY_TYPE,
                entityId: (string) $profile->id,
                payload: ProfileRenewed::payload($profile),
            );

            return $profile;
        });
    }
}
