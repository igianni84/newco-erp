<?php

namespace App\Modules\Parties\Exceptions;

use App\Modules\Parties\Enums\ProducerAgreementStatus;
use RuntimeException;

/**
 * Raised when a ProducerAgreement transition is attempted from a state the FSM does not allow
 * (parties-producer-lifecycle, design L2; party-registry — Requirement: ProducerAgreement Lifecycle).
 *
 * The agreement FSM is `draft → active → superseded | terminated` (Module K PRD § 4.6.1): activation
 * is valid only from `draft`, termination only from `active`. Supersession (`active → superseded`) is
 * NOT a direct operator call — it is driven by activating a replacement in the same `(producer_id,
 * club_id)` scope — so it has no factory here. The transition Action is the SOLE writer of
 * `ProducerAgreement.status`; it re-reads the row `lockForUpdate` inside its transaction and asserts the
 * from-state before writing, so an out-of-state call throws this and the transaction rolls back, leaving
 * the row and the event log unchanged.
 *
 * Localized through Laravel's translator (invariant 12): the English baseline lives in the
 * `producer_agreement` group of `lang/en/parties.php` (keys `cannot_activate` / `cannot_terminate`),
 * with a `:state` placeholder. The offending state token (`$from->value`) is a business enum value, NOT
 * PII (the same discipline as the sibling {@see MissingAgreementProducer} guard). `(string)` coerces the
 * translator return (typed `mixed` by Larastan) to the RuntimeException message contract.
 */
class IllegalProducerAgreementTransition extends RuntimeException
{
    public static function cannotActivate(ProducerAgreementStatus $from): self
    {
        return new self((string) __('parties.producer_agreement.cannot_activate', [
            'state' => $from->value,
        ]));
    }

    public static function cannotTerminate(ProducerAgreementStatus $from): self
    {
        return new self((string) __('parties.producer_agreement.cannot_terminate', [
            'state' => $from->value,
        ]));
    }
}
