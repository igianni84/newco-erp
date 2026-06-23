<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Parties\Enums\ClubCreditState;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Exceptions\ClubCreditRedemptionPrecondition;
use App\Modules\Parties\Exceptions\IllegalClubCreditTransition;
use App\Modules\Parties\Models\ClubCredit;
use App\Modules\Parties\Models\Profile;
use App\Platform\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Redeems part or all of a Club Credit against a purchase — the SOLE writer of the credit `remaining` and of the
 * `active → redeemed` transition, AUDIT-ONLY, recording NO domain event (change club-credit, design L3/L4/L6;
 * party-registry — Requirement: Club Credit Redemption and Carry-Forward; Module K PRD § 11.2 / § 11; DEC-007).
 *
 * K.17 CARRY-FORWARD (§ 11; design L6): given a redeemed `Money`, the new `remaining = remaining − redeemed`. If the
 * new `remaining` is ZERO the credit transitions `active → redeemed` (fully spent); if it stays POSITIVE the credit
 * remains `active` and the balance CARRIES FORWARD to future purchases (the K.17 partial-redemption rule —
 * "carries forward until forfeiture"). Full redemption is the norm; a package exceeding the credit applies the full
 * `remaining` and the Customer pays the difference in cash — but that splitting is a Module S checkout concern, so
 * here an amount exceeding `remaining` is simply rejected (no negative balance — see the over-application guard).
 *
 * FOUR GUARDS, all BEFORE any write (design L6; § 11.2 / § 10.1), each leaving `remaining` and `state` unchanged:
 *   1. the credit is `active` — else {@see IllegalClubCreditTransition::cannotApply} (a `redeemed`/`forfeited`
 *      credit cannot be redeemed; the FSM from-state guard);
 *   2. the redeemed amount's currency equals the credit currency — else
 *      {@see ClubCreditRedemptionPrecondition::currencyMismatch}. This EXPLICIT equality check makes
 *      {@see Money::minus()}'s own currency-mismatch `InvalidArgumentException` unreachable (there is no FX in
 *      Module K — design L6);
 *   3. the redeemed `minorUnits` does not exceed `remaining->minorUnits` — else
 *      {@see ClubCreditRedemptionPrecondition::overApplication} (a negative balance is unrepresentable; `Money`
 *      exposes no `<`/`>`, so the comparison is on the public integer minor units after the same-currency guard —
 *      exact-integer, no float);
 *   4. the owning Profile is NOT `Suspended` — else {@see ClubCreditRedemptionPrecondition::frozenWhileSuspended}.
 *      Suspension FREEZES the credit (no redemption while suspended — AC-K-FSM-2a; § 10.1); it becomes mutable
 *      again on restore. This is the within-module realization of the "frozen while suspended" guarantee the
 *      suspension slice named as a deferred `club-credit` seam — now closed (both entities are Module K, so the
 *      cross-module relation ban does not apply).
 *
 * AUDIT-ONLY (design L3; § 11.4): § 11.4 makes `ClubCreditApplied` MODULE E's event — Module K consumes it and
 * records the resulting state on its own entity — so, exactly as {@see IssueClubCredit} creates the credit
 * recording no event (and as {@see RecordKycVerified} writes `kyc_status` recording none), this Action writes
 * `remaining`/`state` and records NO domain event; the entity state is the launch record. It injects NEITHER
 * `DomainEventRecorder` NOR `ActorContext`, and fabricates NO `ClubCreditApplied` event class (zero-invention).
 *
 * CHECKOUT TRIGGER — DEFERRED MODULE-S SEAM (design L6; § 11.2 / § 11.5; DEC-110 / DEC-111): in production the
 * redemption amount is resolved at checkout by Module S — Offer matching against the issuing Club
 * (`credit.profile.club_id ∈ offer.club_ids`), the coupon mutual-exclusion (one coupon XOR one Club Credit per
 * checkout), auto-apply, and the Hold-gated price resolution. The data Module K exposes for that decision is the
 * credit's `active` state, its `remaining`, its currency and its issuing Club; the checkout DECISION and the
 * Module-S eligibility read contract are the Module-S seam — not built here. `ApplyClubCredit` ships as the
 * within-module writer, invoked by that seam later and directly in tests now.
 *
 * Transaction-safe (design L4, mirroring {@see IssueClubCredit}): inside ONE {@see DB::transaction} it re-reads the
 * credit and its owning Profile `->lockForUpdate()` (a real row lock on PostgreSQL serializing concurrent
 * redemptions, a no-op under SQLite — the guards carry correctness either way), runs the four guards, then writes
 * the decremented `remaining` (and `redeemed` when fully spent). The ClubCredit model stays persistence-only
 * (`$guarded = []`); this Action is the sole `remaining`/`state` writer for redemption.
 */
class ApplyClubCredit
{
    public function handle(int $clubCreditId, Money $redeemed): ClubCredit
    {
        return DB::transaction(function () use ($clubCreditId, $redeemed): ClubCredit {
            // Transaction-locked re-read of the credit and its owning Profile so two concurrent redemptions
            // serialize on PostgreSQL; the guards below carry correctness either way (the lock is a no-op on SQLite).
            $credit = ClubCredit::query()->whereKey($clubCreditId)->lockForUpdate()->firstOrFail();
            $profile = Profile::query()->whereKey($credit->profile_id)->lockForUpdate()->firstOrFail();

            // Guard 1 — FSM from-state (§ 11.2; design L6): redemption is reachable only from `active`; a
            // `redeemed`/`forfeited` credit cannot be redeemed.
            if (! $credit->state->isActive()) {
                throw IllegalClubCreditTransition::cannotApply($credit->state);
            }

            // Guard 2 — currency match (design L6): the redeemed amount must share the credit currency. The explicit
            // check (there is no FX in Module K) makes Money::minus's own mismatch exception unreachable below.
            if ($redeemed->currency !== $credit->remaining->currency) {
                throw ClubCreditRedemptionPrecondition::currencyMismatch($credit->remaining->currency, $redeemed->currency);
            }

            // Guard 3 — no over-application (design L6; AC-K-J-18): the redeemed amount may not exceed `remaining`
            // (no negative balance). Money exposes no comparison operator, so compare the public integer minor units
            // after the same-currency guard above — exact-integer, never a float (invariant 6).
            if ($redeemed->minorUnits > $credit->remaining->minorUnits) {
                throw ClubCreditRedemptionPrecondition::overApplication($credit->id);
            }

            // Guard 4 — freeze-while-suspended (AC-K-FSM-2a; § 10.1; design L6): no redemption while the owning
            // Profile is suspended; the credit becomes mutable again once it is restored.
            if ($profile->state === ProfileState::Suspended) {
                throw ClubCreditRedemptionPrecondition::frozenWhileSuspended($credit->id);
            }

            // K.17 carry-forward (§ 11; design L6): decrement `remaining`; a zero balance fully spends the credit
            // (`active → redeemed`), a positive balance keeps it `active` and carries forward. AUDIT-ONLY — no event
            // (design L3; § 11.4). The over-application guard guarantees `remaining` never goes negative.
            $remaining = $credit->remaining->minus($redeemed);

            $credit->update([
                'remaining' => $remaining,
                'state' => $remaining->minorUnits === 0 ? ClubCreditState::Redeemed : ClubCreditState::Active,
            ]);

            return $credit;
        });
    }
}
