<?php

namespace App\Modules\Parties\Enums;

/**
 * The Club Credit lifecycle domain (party-registry — Requirement: Club Credit Entity
 * and One-Active-Per-Profile Invariant; design club-credit L1/L4/L5).
 *
 * The spec's verbatim three-state Club Credit FSM `active → redeemed | forfeited`
 * (Module K PRD § 11). A Club Credit — the per-Profile prepayment instrument the
 * membership fee converts into (DEC-007) — is born `active` at issuance; `redeemed`
 * and `forfeited` are reached only through the within-module writer Actions, and
 * `redeemed → active` is reachable only via RestoreClubCredit (a downstream
 * order-cancellation effect, § 11, not a Club Credit primitive). At most one `active`
 * credit per Profile exists at any moment (the structural partial-unique invariant).
 *
 * Terminality has two notions here, and they differ — the source of the nuance the
 * helpers below pin down: `forfeited` is **absolutely terminal** (no outgoing edge —
 * § 11.3, at most one forfeiture per lifetime), whereas `redeemed` cannot be
 * forfeited (ForfeitClubCredit guards on `active`) yet is **restore-reachable** back
 * to `active`. So `redeemed` is "terminal for forfeiture" but not absolutely
 * terminal.
 *
 * - case name    = the state in PascalCase (Parties vocabulary)
 * - backing value = the persisted token (the column value)
 */
enum ClubCreditState: string
{
    case Active = 'active';
    case Redeemed = 'redeemed';
    case Forfeited = 'forfeited';

    /**
     * Whether the credit is live and value-mutable — the from-state of every
     * value-moving transition. ApplyClubCredit (redemption) and ForfeitClubCredit
     * both guard on `isActive()` before any write; IssueClubCredit creates the row
     * `active` and the one-active partial index admits it only when no other `active`
     * credit covers the Profile. The Action is the sole writer; this predicate is the
     * readable from-state guard (mirrors KycStatus::clears() / HoldType::autoLiftable()).
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /**
     * Whether the credit has reached an **absolutely terminal** state — one with no
     * outgoing transition. Only `forfeited` qualifies (§ 11.3 — at most one forfeiture
     * per lifetime; nothing reactivates a forfeited credit). `redeemed` is NOT terminal
     * here: although it cannot be forfeited (forfeiture requires `active`) and is an
     * end-of-redemption state, RestoreClubCredit makes `redeemed → active` reachable on
     * an order cancellation (§ 11) — so a redeemed credit is "terminal for forfeiture"
     * yet restore-reachable. `active` is not terminal.
     */
    public function isTerminal(): bool
    {
        return $this === self::Forfeited;
    }
}
