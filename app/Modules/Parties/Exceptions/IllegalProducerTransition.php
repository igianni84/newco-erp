<?php

namespace App\Modules\Parties\Exceptions;

use App\Modules\Parties\Enums\ProducerStatus;
use RuntimeException;

/**
 * Raised when a Producer transition is attempted from a state the FSM does not allow
 * (parties-producer-lifecycle, design L2; party-registry — Requirement: Producer Lifecycle).
 *
 * The Producer FSM is linear — `draft → active → retired` (Module K PRD § 4.4): activation is valid
 * only from `draft`, retirement only from `active`. The transition Action is the SOLE writer of
 * `Producer.status`; it re-reads the row `lockForUpdate` inside its transaction and asserts the
 * from-state before writing, so an out-of-state call throws this and the transaction rolls back —
 * the row and the event log are left unchanged (nothing is recorded).
 *
 * The reason is localized through Laravel's translator (CLAUDE.md invariant 12 — no hardcoded
 * user-facing strings): the English baseline lives in the `producer` group of `lang/en/parties.php`
 * (keys `cannot_activate` / `cannot_retire`), with a `:state` placeholder. The offending state token
 * (`$from->value`) is a business enum value, NOT PII — so, like the producer-id sibling guards
 * ({@see MissingClubProducer}) and unlike the PII-omitting {@see DuplicateCustomerEmail}, it is
 * interpolated to make the reason self-documenting. `(string)` coerces the translator return (typed
 * `mixed` by Larastan) to the RuntimeException message contract.
 */
class IllegalProducerTransition extends RuntimeException
{
    public static function cannotActivate(ProducerStatus $from): self
    {
        return new self((string) __('parties.producer.cannot_activate', [
            'state' => $from->value,
        ]));
    }

    public static function cannotRetire(ProducerStatus $from): self
    {
        return new self((string) __('parties.producer.cannot_retire', [
            'state' => $from->value,
        ]));
    }
}
