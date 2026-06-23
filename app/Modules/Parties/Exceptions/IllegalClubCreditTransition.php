<?php

namespace App\Modules\Parties\Exceptions;

use App\Modules\Parties\Enums\ClubCreditState;
use RuntimeException;

/**
 * Raised when a Club Credit FSM transition is attempted from a state the machine does not allow
 * (change club-credit, design L4/L6/L7; party-registry — Requirements: Club Credit Redemption and
 * Carry-Forward, Club Credit Forfeiture and Restoration; Module K PRD § 11).
 *
 * The Club Credit FSM is `active → redeemed | forfeited`, with the order-cancellation restore edge
 * `redeemed → active` ({@see ClubCreditState}). Each value-moving transition has a from-state guard the
 * within-module writer Action asserts before any write: {@see ApplyClubCredit} and {@see ForfeitClubCredit}
 * require `active` ({@see cannotApply} / `cannotForfeit`), {@see RestoreClubCredit} requires `redeemed`
 * (`cannotRestore`). The Actions are the SOLE writers of the credit `state`/`remaining`; each re-reads the row
 * `lockForUpdate` inside its transaction and asserts the from-state, so a disallowed call throws this BEFORE any
 * write and the transaction rolls back — the credit (`state` and `remaining`) is left unchanged. This is the
 * FSM from-state guard ONLY; the redemption value/context preconditions on an otherwise-`active` credit
 * (currency-match, over-application, the frozen-while-suspended freeze) are the sibling
 * {@see ClubCreditRedemptionPrecondition}, mirroring how issuance splits its from-state-less preconditions into
 * {@see ClubCreditIssuancePrecondition}. The restore from-state factory lands with its Action (task 4.2); this
 * exception ships {@see cannotApply} (task 3.1) and {@see cannotForfeit} (task 4.1).
 *
 * The reason is localized through Laravel's translator (CLAUDE.md invariant 12 — no hardcoded user-facing
 * strings): the English baseline lives in the `club_credit` group of `lang/en/parties.php` (key `cannot_apply`,
 * added in the change's i18n task 5.2), with a `:state` placeholder. The offending state token (`$from->value`)
 * is a business enum value, NOT PII — so, like the sibling {@see IllegalProfileTransition}, it is interpolated to
 * make the reason self-documenting. `(string)` coerces the translator return (typed `mixed` by Larastan) to the
 * RuntimeException message contract.
 */
class IllegalClubCreditTransition extends RuntimeException
{
    /**
     * Redemption ({@see ApplyClubCredit}) rejected because the credit is not `active` — the only from-state a
     * redeemed amount can be applied from (§ 11.2; design L6). A `redeemed` or `forfeited` credit cannot be
     * redeemed.
     */
    public static function cannotApply(ClubCreditState $from): self
    {
        return new self((string) __('parties.club_credit.cannot_apply', [
            'state' => $from->value,
        ]));
    }

    /**
     * Forfeiture ({@see ForfeitClubCredit}) rejected because the credit is not `active` — the only from-state a
     * credit can be forfeited from (§ 11.3; design L4). A `redeemed` credit cannot be forfeited, and `forfeited`
     * is absolutely terminal (at most one forfeiture per Club Credit lifetime), so this also rejects a second
     * forfeit on an already-`forfeited` credit.
     */
    public static function cannotForfeit(ClubCreditState $from): self
    {
        return new self((string) __('parties.club_credit.cannot_forfeit', [
            'state' => $from->value,
        ]));
    }
}
