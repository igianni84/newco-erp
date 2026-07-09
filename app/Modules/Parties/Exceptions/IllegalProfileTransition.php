<?php

namespace App\Modules\Parties\Exceptions;

use App\Modules\Parties\Enums\ProfileState;
use RuntimeException;

/**
 * Raised when a Profile membership transition is attempted from a state the FSM does not allow
 * (parties-membership-activation, design L2/L4; parties-membership-suspension, design L4/L5;
 * party-registry — Requirements: Profile Membership Approval, Profile Activation, Profile Suspension
 * and Restoration, Profile Lapse and Grace Renewal, Profile Cancellation and Deactivation).
 *
 * The retained demand-side Profile FSM is `applied → approved | rejected → active` (Module K PRD
 * § 4.2.1): approval and decline are valid only from `applied`, activation only from `approved`.
 * The suspension subset adds the status edges off `active`: `active ↔ suspended`
 * ({@see cannotSuspend} / {@see cannotReactivate}), `active → lapsed → active` grace
 * ({@see cannotLapse} / {@see cannotRenew} — the renewal also rejects a from-state past the
 * 30-day grace window, DEC-034), `active | lapsed → cancelled` ({@see cannotCancel}) and
 * `active → inactive` ({@see cannotDeactivate}). The transition Actions (`ApproveProfile` /
 * `DeclineProfile` / `ActivateProfile`, and the suspension Actions `SuspendProfile` /
 * `ReactivateProfile` / `LapseProfile` / `RenewProfile` / `CancelProfile` / `DeactivateProfile`)
 * are the SOLE writers of `Profile.state`; each re-reads the row `lockForUpdate` inside its
 * transaction and asserts the from-state before writing, so a disallowed call throws this and the
 * transaction rolls back — the row and the event log are left unchanged. Approve/decline and
 * cancellation are **audit-only** (they record no Profile event — § 15.2 names none; L2); the other
 * edges record their verbatim § 15.2 event.
 *
 * The reason is localized through Laravel's translator (CLAUDE.md invariant 12 — no hardcoded
 * user-facing strings): the English baseline lives in the `profile` group of `lang/en/parties.php`
 * (keys `cannot_approve` / `cannot_reject` / `cannot_activate` / `cannot_suspend` /
 * `cannot_reactivate` / `cannot_lapse` / `cannot_renew` / `cannot_cancel` / `cannot_deactivate`),
 * with a `:state` placeholder. Alongside them sits `club_at_capacity` — the Hero-Package capacity
 * rejection ({@see clubAtCapacity}), which interpolates `:capacity` and `:occupied` as well, and is
 * the one reason of this class also authored in `lang/it/parties.php`: it is refused by the Club's
 * seat ledger rather than by the operator's own click, so it must say why in the operator's language.
 * The offending state token (`$from->value`) is a business enum value, NOT PII — so, like the sibling
 * {@see IllegalProducerTransition}, it is interpolated to make the reason self-documenting.
 * `(string)` coerces the translator return (typed `mixed` by Larastan) to the RuntimeException
 * message contract.
 */
class IllegalProfileTransition extends RuntimeException
{
    public static function cannotApprove(ProfileState $from): self
    {
        return new self((string) __('parties.profile.cannot_approve', [
            'state' => $from->value,
        ]));
    }

    public static function cannotReject(ProfileState $from): self
    {
        return new self((string) __('parties.profile.cannot_reject', [
            'state' => $from->value,
        ]));
    }

    public static function cannotActivate(ProfileState $from): self
    {
        return new self((string) __('parties.profile.cannot_activate', [
            'state' => $from->value,
        ]));
    }

    public static function cannotSuspend(ProfileState $from): self
    {
        return new self((string) __('parties.profile.cannot_suspend', [
            'state' => $from->value,
        ]));
    }

    public static function cannotReactivate(ProfileState $from): self
    {
        return new self((string) __('parties.profile.cannot_reactivate', [
            'state' => $from->value,
        ]));
    }

    public static function cannotLapse(ProfileState $from): self
    {
        return new self((string) __('parties.profile.cannot_lapse', [
            'state' => $from->value,
        ]));
    }

    /**
     * Renewal rejected — either the Profile is not in `lapsed`, or it is `lapsed` but the 30-day grace
     * window has elapsed (DEC-034; design L5). The `:state` reason names the from-state and the grace rule.
     */
    public static function cannotRenew(ProfileState $from): self
    {
        return new self((string) __('parties.profile.cannot_renew', [
            'state' => $from->value,
        ]));
    }

    public static function cannotCancel(ProfileState $from): self
    {
        return new self((string) __('parties.profile.cannot_cancel', [
            'state' => $from->value,
        ]));
    }

    public static function cannotDeactivate(ProfileState $from): self
    {
        return new self((string) __('parties.profile.cannot_deactivate', [
            'state' => $from->value,
        ]));
    }

    /**
     * The Hero-Package capacity rejection — ONE factory shared by the only two seat-consuming transitions that,
     * at parity, have no transition left to make (parties-hero-package, design D8; party-registry — Requirement:
     * Hero Package Capacity Invariant; ADR 2026-07-09-hero-package-capacity-seat-set-and-waitinglist):
     *   - `ApproveProfile` on a Profile ALREADY in `waiting_list` whose Club is STILL full. It throws rather than
     *     no-opping idempotently: a silent no-op is indistinguishable from a defect to the operator who clicked.
     *   - `RenewProfile` on a `lapsed` Profile inside its 30-day grace: `lapsed → active` RE-CONSUMES a seat
     *     (canon § 13.1), and canon draws no `Lapsed → WaitingList` edge to divert onto — so the Profile stays
     *     `lapsed`, its `lapsed_at` untouched and its grace clock still running, and the operator reads why.
     *
     * An `applied` Profile at parity is NOT rejected here: a transition exists, so `ApproveProfile` DIVERTS it
     * into `waiting_list` and records `WaitingListJoined`. This factory is for the absence of an edge, never for
     * the absence of a seat alone.
     *
     * `$capacity` is typed `int`, not `?int`, because an UNCAPPED Club (`null` capacity) can never oversell and
     * so never reaches this throw. `$occupiedSeats` is the count the caller already took under the `parties_clubs`
     * row lock (`ClubSeatOccupancy::lockAndCountOccupiedSeats()`), handed in rather than counted a second time —
     * which also means the number in the message is exactly the number the gate decided on. Both are Club-level
     * cardinals and `$from->value` is a business enum token: the reason names the seat ledger, never a customer.
     */
    public static function clubAtCapacity(ProfileState $from, int $capacity, int $occupiedSeats): self
    {
        return new self((string) __('parties.profile.club_at_capacity', [
            'state' => $from->value,
            'capacity' => $capacity,
            'occupied' => $occupiedSeats,
        ]));
    }
}
