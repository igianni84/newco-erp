<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Parties\Enums\ClubCreditState;
use App\Modules\Parties\Exceptions\IllegalClubCreditTransition;
use App\Modules\Parties\Models\ClubCredit;
use Illuminate\Support\Facades\DB;

/**
 * Forfeits a Club Credit — the SOLE writer of the `active → forfeited` transition, AUDIT-ONLY, recording NO
 * domain event (change club-credit, design L3/L4/L5; party-registry — Requirement: Club Credit Forfeiture and
 * Restoration; Module K PRD § 11.3 / § 11.4).
 *
 * SINGLE GUARD, BEFORE any write (§ 11.3; design L4): the credit must be `active` — else
 * {@see IllegalClubCreditTransition::cannotForfeit} (the second FSM from-state edge, after {@see ApplyClubCredit}'s
 * `cannotApply`). A `redeemed` or `forfeited` credit cannot be forfeited; in particular `forfeited` is ABSOLUTELY
 * TERMINAL (§ 11.3 — at most one forfeiture per Club Credit lifetime), so a second forfeit, an apply, or a restore
 * on a forfeited credit is rejected by its respective from-state guard. There is NO freeze-while-suspended guard
 * here: unlike redemption (AC-K-FSM-2a — {@see ApplyClubCredit}), forfeiture is a system/operator effect that must
 * still fire on a suspended Profile's credit (e.g. year-end lapse), so it reads only the credit `state`, never the
 * owning Profile.
 *
 * AUDIT-ONLY (design L3; § 11.4): § 11.4 makes `ClubCreditForfeited` MODULE E's event — Module K consumes it and
 * records the resulting state on its own entity — so, exactly as {@see IssueClubCredit} creates the credit and
 * {@see ApplyClubCredit} redeems it recording no event (and as {@see RecordKycVerified} writes `kyc_status`
 * recording none), this Action writes `state = forfeited` and records NO domain event; the entity state is the
 * launch record. It injects NEITHER `DomainEventRecorder` NOR `ActorContext`, and fabricates NO `ClubCreditForfeited`
 * event class (zero-invention).
 *
 * FORFEITURE TRIGGERS — DEFERRED SEAMS (design L5; § 11.3): in production forfeiture is driven by four upstream
 * triggers, NONE wired by this change — this Action is the within-module writer they will each invoke:
 *   1. YEAR-END LAPSE — a daily background job auto-forfeits credits whose `valid_to` has passed without full
 *      redemption (a SCHEDULER seam, mirroring the `LapseProfile` validity-period seam);
 *   2. RENEWAL-TRIGGERED REPLACEMENT — at renewal the prior period's active credit is auto-forfeited and replaced
 *      (FORFEIT-BEFORE-ISSUE, sequenced within the Module-E renewal-time `MembershipFeePaid` consumption);
 *   3. PROFILE CANCELLATION — when a Profile reaches terminal `Cancelled`, its active credit is forfeited (with an
 *      optional per-Club grace period — a within-module follow-on cascade);
 *   4. CLUB CLOSURE — when the issuing Club closes mid-credit-life the residual balance is converted to Discovery
 *      store credit at face value, 12-month validity (DEC-043) — an operation OWNED BY MODULE S; Module K's role
 *      ends at the upstream cancellation/closure signal (AC-K-XM-23). This Action only forfeits; it runs NO
 *      conversion math, and leaves `remaining` intact (the residual balance is the Module-S conversion input).
 *
 * The FORFEIT-BEFORE-ISSUE ordering (trigger 2) is nonetheless provable at launch WITHOUT the renewal listener: the
 * one-active partial index makes {@see IssueClubCredit} reject while an `active` credit exists (design L1), so the
 * only way to re-issue is `ForfeitClubCredit` then `IssueClubCredit` — the exact ordering the renewal listener will
 * perform, exercised directly in the forfeiture tests (the pair lands in task 4.3).
 *
 * Transaction-safe (design L4, mirroring {@see ApplyClubCredit}): inside ONE {@see DB::transaction} it re-reads the
 * credit `->lockForUpdate()` (a real row lock on PostgreSQL serializing a concurrent forfeit/redeem, a no-op under
 * SQLite — the from-state guard carries correctness either way), asserts the from-state, then writes `forfeited`.
 * The ClubCredit model stays persistence-only (`$guarded = []`); this Action is the sole `active → forfeited` writer.
 */
class ForfeitClubCredit
{
    public function handle(int $clubCreditId): ClubCredit
    {
        return DB::transaction(function () use ($clubCreditId): ClubCredit {
            // Transaction-locked re-read so a concurrent forfeit/redeem serializes on PostgreSQL; the from-state
            // guard below carries correctness either way (the lock is a no-op on SQLite).
            $credit = ClubCredit::query()->whereKey($clubCreditId)->lockForUpdate()->firstOrFail();

            // Guard — FSM from-state (§ 11.3; design L4): forfeiture is reachable only from `active`. A `redeemed`
            // or `forfeited` credit cannot be forfeited; `forfeited` is absolutely terminal (at most one forfeiture
            // per lifetime — § 11.3), so this same guard rejects a second forfeit.
            if (! $credit->state->isActive()) {
                throw IllegalClubCreditTransition::cannotForfeit($credit->state);
            }

            // AUDIT-ONLY (design L3; § 11.4): set the terminal `forfeited` state and record NO domain event. The
            // `remaining` balance is left intact — forfeiture is a state change; any residual-balance handling (the
            // DEC-043 Club-closure conversion at face value) is the Module-S seam, not zeroed here.
            $credit->update(['state' => ClubCreditState::Forfeited]);

            return $credit;
        });
    }
}
