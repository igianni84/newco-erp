<?php

namespace App\Modules\Parties\Exceptions;

use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\ProducerStatus;
use RuntimeException;

/**
 * Raised when a Producer transition is attempted from a state the FSM does not allow, or when activation
 * is blocked by an uncleared KYC verdict (parties-producer-lifecycle, design L2; parties-compliance,
 * design L5; party-registry — Requirement: Producer Lifecycle).
 *
 * The Producer FSM is linear — `draft → active → retired` (Module K PRD § 4.4): activation is valid only
 * from `draft` and only when KYC is **cleared** (parties-compliance — `kyc_not_cleared`; § 4.4 / BR-K-Producer-2),
 * retirement only from `active`. The transition Action is the SOLE writer of `Producer.status`; it re-reads
 * the row `lockForUpdate` inside its transaction and asserts the from-state (then, for activation, the
 * KYC-cleared gate) before writing, so a disallowed call throws this and the transaction rolls back — the
 * row and the event log are left unchanged (nothing is recorded).
 *
 * The reason is localized through Laravel's translator (CLAUDE.md invariant 12 — no hardcoded
 * user-facing strings): the English baseline lives in the `producer` group of `lang/en/parties.php`
 * (keys `cannot_activate` / `cannot_retire` / `kyc_not_cleared`), with a `:state` placeholder. The offending
 * state token (`$from->value`) is a business enum value, NOT PII — so, like the producer-id sibling guards
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

    /**
     * Activation rejected because the Producer's KYC is not cleared (parties-compliance, design L5;
     * § 4.4 / BR-K-Producer-2). Reached only from `draft` with a `pending`/`rejected` `kyc_status` — the
     * cleared states (`verified`, `not_required`) and a NULL `kyc_status` (never touched, treated as cleared
     * for additivity — ADR 2026-06-17) never reach this throw, so the offending `$from` is a non-null
     * blocking `KycStatus`. `$from->value` (`pending`/`rejected`) is a business enum token, not PII.
     */
    public static function kycNotCleared(KycStatus $from): self
    {
        return new self((string) __('parties.producer.kyc_not_cleared', [
            'state' => $from->value,
        ]));
    }
}
