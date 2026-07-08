<?php

namespace App\Modules\Parties\Exceptions;

use RuntimeException;

/**
 * Raised when activating a ProducerAgreement would leave a Producer with BOTH a Producer-wide (`club_id`
 * NULL) and a per-Club (`club_id` set) agreement `active` at the same time — the cross-shape mutual-exclusion
 * of BR-K-Agreement-1 clause 2 (change parties-module-k-br-guards, design D2/R1; party-registry — Requirement:
 * ProducerAgreement Lifecycle; Module K PRD § 4.6.1). `ActivateProducerAgreement` (task 3.3) throws this
 * pre-write when an `active` agreement of the OPPOSITE shape already exists for the Producer, so the agreement
 * being activated stays `draft`, the prior stays `active`, and the event log is left unchanged; the operator
 * SHALL first terminate/supersede the existing-shape agreement. Same-scope supersession (the `(producer_id,
 * club_id)` tuple) is a separate mechanic and is untouched.
 *
 * Two direction-aware factories, each resolving localized copy (CLAUDE.md invariant 12 — no hardcoded
 * user-facing strings) from the `producer_agreement` group of `lang/en/parties.php`:
 *   - {@see producerWideBlockedByClubScope()} — a Producer-wide activation blocked by an active per-Club agreement.
 *   - {@see clubScopeBlockedByProducerWide()} — a per-Club activation blocked by an active Producer-wide agreement.
 *
 * The copy names only the violated RULE and the operator-facing `:producer` id (an identity reference, NOT PII —
 * the `MissingClubProducer` / `DuplicateProfileForClub` discipline); which agreements are involved lives on the
 * event/audit rows, the system of record. `(string)` coerces the translator return (typed `mixed` by Larastan)
 * to the RuntimeException message contract, exactly as the sibling guards do.
 */
class ProducerAgreementScopeConflict extends RuntimeException
{
    public static function producerWideBlockedByClubScope(int $producerId): self
    {
        return self::build('scope_conflict_producer_wide', $producerId);
    }

    public static function clubScopeBlockedByProducerWide(int $producerId): self
    {
        return self::build('scope_conflict_club_scope', $producerId);
    }

    private static function build(string $key, int $producerId): self
    {
        return new self((string) __("parties.producer_agreement.{$key}", ['producer' => $producerId]));
    }
}
