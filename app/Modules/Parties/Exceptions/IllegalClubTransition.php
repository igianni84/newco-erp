<?php

namespace App\Modules\Parties\Exceptions;

use App\Modules\Parties\Enums\ClubStatus;
use RuntimeException;

/**
 * Raised when a Club transition is attempted from a state the FSM does not allow
 * (parties-producer-lifecycle, design L2; party-registry — Requirement: Club Lifecycle).
 *
 * The Club FSM is `active → sunset → closed` (Module K PRD § 4.3): sunset is valid only from `active`,
 * closure only from `sunset` — an `active` Club cannot be closed directly, it must pass through `sunset`.
 * The transition Action is the SOLE writer of `Club.status`; it re-reads the row `lockForUpdate` inside
 * its transaction and asserts the from-state before writing, so an out-of-state call throws this and the
 * transaction rolls back, leaving the row and the event log unchanged. {@see SunsetClub} is the single
 * `ClubSunset` writer, so this guard fires identically whether sunset is a standalone operator action or
 * the per-Club step of the Producer-retirement cascade.
 *
 * Localized through Laravel's translator (invariant 12): the English baseline lives in the `club` group
 * of `lang/en/parties.php` (keys `cannot_sunset` / `cannot_close`), with a `:state` placeholder. The
 * offending state token (`$from->value`) is a business enum value, NOT PII (the same discipline as the
 * sibling {@see MissingClubProducer} guard). `(string)` coerces the translator return (typed `mixed` by
 * Larastan) to the RuntimeException message contract.
 */
class IllegalClubTransition extends RuntimeException
{
    public static function cannotSunset(ClubStatus $from): self
    {
        return new self((string) __('parties.club.cannot_sunset', [
            'state' => $from->value,
        ]));
    }

    public static function cannotClose(ClubStatus $from): self
    {
        return new self((string) __('parties.club.cannot_close', [
            'state' => $from->value,
        ]));
    }
}
