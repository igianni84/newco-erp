<?php

namespace App\Modules\Parties\Exceptions;

use RuntimeException;

/**
 * Raised when an update dirties a review-governed descriptive field of a Producer — `name`, `description`,
 * `region`, `website` — while the persisted `status` is `active` (change parties-module-k-br-guards, design D9;
 * party-registry — Requirement: Producer Review-Governed Content Lock; BR-K-Producer-5 / canon MVP-DEC-022 — ADR
 * 2026-07-07-adopt-mvp-dec-022-club-membership-governance). A model-level, path-complete `Producer` `updating`
 * chokepoint (task 5.2, the RM-24 immutability-guard pattern) throws this so the Producer and its content are
 * left unchanged, regardless of the writing surface. A `draft` Producer sets this content freely; a transition
 * dirtying only `status`/`kyc_status`/`version` (activation, retirement, KYC) passes untouched.
 *
 * This is the INTERIM safety core of BR-K-Producer-5 — unreviewed content never publishes on an `active`
 * Producer. The full "edit re-enters the Creator → Reviewer → Approver workflow" UX is deferred (no Producer
 * `reviewed` state / content-edit path exists today, RM-06 / RM-14 precedent); when it lands, this hard lock is
 * REPLACED by the edit-re-arms-review behavior.
 *
 * The reason is localized (CLAUDE.md invariant 12) from the `producer` group of `lang/en/parties.php`. It names
 * the locked field-set and the RULE, interpolating only the operator-facing `:producer` id (an identity
 * reference, NOT PII). `(string)` coerces the translator return (typed `mixed` by Larastan) to the
 * RuntimeException message contract.
 */
class ProducerReviewGovernedContentLocked extends RuntimeException
{
    public static function whileActive(int $producerId): self
    {
        return new self((string) __('parties.producer.review_governed_content_locked', [
            'producer' => $producerId,
        ]));
    }
}
