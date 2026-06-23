<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Parties\Enums\ClubCreditState;
use App\Modules\Parties\Exceptions\ClubCreditRestorePrecondition;
use App\Modules\Parties\Exceptions\IllegalClubCreditTransition;
use App\Modules\Parties\Models\ClubCredit;
use App\Modules\Parties\Models\Profile;
use Illuminate\Support\Facades\DB;

/**
 * Restores a redeemed Club Credit — the SOLE writer of the `redeemed → active` transition, AUDIT-ONLY, recording NO
 * domain event (change club-credit, design L3/L4/L7; party-registry — Requirement: Club Credit Forfeiture and
 * Restoration; Module K PRD § 11 / § 11.4). This is the downstream effect of an ORDER CANCELLATION within the
 * cancellation window: a credit that was fully redeemed against an order is re-opened when that order is cancelled
 * (§ 11 — `redeemed → active` is reachable ONLY here, never a Club Credit primitive).
 *
 * TWO GUARDS, both BEFORE any write (design L7; § 11), each leaving `state` and `remaining` unchanged on rejection:
 *   1. the credit is `redeemed` — else {@see IllegalClubCreditTransition::cannotRestore} (the FSM from-state guard:
 *      restoration departs only from `redeemed`; an `active` credit needs no restoration and a `forfeited` credit is
 *      ABSOLUTELY TERMINAL — § 11.3 — so neither can be restored);
 *   2. the owning Profile holds no OTHER `active` Club Credit — else
 *      {@see ClubCreditRestorePrecondition::profileHasActiveCredit}. The one-active-per-Profile invariant (design L1)
 *      is RESPECTED, not violated: if a replacement credit was already issued (e.g. at renewal — the
 *      forfeit-before-issue path) reactivating this one would breach the partial unique index
 *      `(profile_id) WHERE state = 'active'`, so restoration is refused with a localized exception rather than left to
 *      abort on the index. The credit being restored is `redeemed`, so it is outside the `active` scope and never
 *      counts as its own conflict (until the write below flips it).
 *
 * REMAINING RESTORED (design L7; § 11.2 / § 11 K.17): a credit reaches `redeemed` ONLY by full spend
 * ({@see ApplyClubCredit} sets `redeemed` exactly when `remaining` hits zero — a partial redemption stays `active`
 * and carries the balance forward), so a `redeemed` credit always has `remaining = 0`. Restoration re-opens the FULL
 * face value: `remaining = amount` (the original issued credit; full redemption is the norm — § 11.2). Module K
 * restores to face value; any PARTIAL/per-order restoration intelligence is the Module-S order-cancellation seam's
 * concern (it alone knows the cancelled order's applied amount), not this within-module writer.
 *
 * AUDIT-ONLY (design L3; § 11.4): § 11.4 makes `ClubCreditRestored` MODULE E's event — Module K consumes it and
 * records the resulting state on its own entity — so, exactly as {@see ApplyClubCredit} redeems and
 * {@see ForfeitClubCredit} forfeits recording no event (and as {@see RecordKycVerified} writes `kyc_status` recording
 * none), this Action writes `state`/`remaining` and records NO domain event; the entity state is the launch record.
 * It injects NEITHER `DomainEventRecorder` NOR `ActorContext`, and fabricates NO `ClubCreditRestored` event class
 * (zero-invention).
 *
 * ORDER-CANCELLATION TRIGGER — DEFERRED MODULE-S SEAM (design L7; § 11): in production restoration is driven by Module
 * S when an order that consumed the credit is cancelled within the cancellation window. Module S does not exist, so
 * the trigger that would invoke this Action is a documented Module-S seam — no Module-S contract is fabricated.
 * `RestoreClubCredit` ships as the within-module writer, invoked by that seam later and directly in tests now —
 * exactly as {@see ApplyClubCredit} ships the redemption write whose checkout trigger is a Module-S seam.
 *
 * Transaction-safe (design L4, mirroring {@see ApplyClubCredit}): inside ONE {@see DB::transaction} it re-reads the
 * credit and its owning Profile `->lockForUpdate()` (a real row lock on PostgreSQL serializing a concurrent
 * restore/issue on the same Profile — the very race the one-active guard defends; a no-op under SQLite, where the
 * guard plus the partial index carry correctness), runs the two guards, then writes `active` + the restored
 * `remaining`. The ClubCredit model stays persistence-only (`$guarded = []`); this Action is the sole
 * `redeemed → active` writer.
 */
class RestoreClubCredit
{
    public function handle(int $clubCreditId): ClubCredit
    {
        return DB::transaction(function () use ($clubCreditId): ClubCredit {
            // Transaction-locked re-read of the credit and its owning Profile so a concurrent restore/issue on the
            // same Profile serializes on PostgreSQL (the one-active race); the guards below plus the partial index
            // carry correctness either way (the lock is a no-op on SQLite).
            $credit = ClubCredit::query()->whereKey($clubCreditId)->lockForUpdate()->firstOrFail();
            $profile = Profile::query()->whereKey($credit->profile_id)->lockForUpdate()->firstOrFail();

            // Guard 1 — FSM from-state (§ 11; design L7): restoration is reachable only from `redeemed` (the
            // order-cancellation edge `redeemed → active`). An `active` credit needs no restoration; `forfeited` is
            // absolutely terminal — neither can be restored.
            if ($credit->state !== ClubCreditState::Redeemed) {
                throw IllegalClubCreditTransition::cannotRestore($credit->state);
            }

            // Guard 2 — one-active-per-Profile precondition (design L1/L7): the Profile must hold no OTHER `active`
            // credit. Reactivating this one while a replacement exists would breach the partial unique index, so
            // reject with a localized exception rather than abort on the violation. The credit here is `redeemed`, so
            // it is outside the `active` scope and never counts as its own conflict.
            if ($profile->activeClubCredit()->exists()) {
                throw ClubCreditRestorePrecondition::profileHasActiveCredit($credit->id);
            }

            // AUDIT-ONLY (design L3; § 11.4): reactivate the credit (`redeemed → active`) and restore the FULL
            // balance — a `redeemed` credit was fully spent (`remaining = 0`), so `remaining = amount` re-opens the
            // face value (design L7; full-redemption norm — § 11.2). Records NO domain event.
            $credit->update([
                'state' => ClubCreditState::Active,
                'remaining' => $credit->amount,
            ]);

            return $credit;
        });
    }
}
