<?php

namespace App\Modules\Parties\Exceptions;

use RuntimeException;

/**
 * Raised when a ProducerAgreement creation names no existing Producer (parties-core, design D3/D4;
 * party-registry — Requirement: ProducerAgreement). A ProducerAgreement references EXACTLY ONE Producer
 * (§ 4.6, required); a creation whose `producer_id` matches no Producer is rejected.
 *
 * The within-module FK on `parties_producer_agreements.producer_id` is the true structural guard (it would
 * reject the insert with an integrity error); this in-transaction pre-check throws first to surface a clean,
 * operator-facing reason instead of a raw FK violation (the same belt-and-braces pattern as the Club
 * {@see MissingClubProducer} guard).
 *
 * The reason is localized through Laravel's translator (CLAUDE.md invariant 12 — no hardcoded user-facing
 * strings): the English baseline lives in `lang/en/parties.php`, other locales fall back per-key. The
 * `producer_id` placed in the message is an operator-facing identity reference, not PII. `(string)` coerces
 * the translator return (typed `mixed` by Larastan) to the RuntimeException message contract.
 */
class MissingAgreementProducer extends RuntimeException
{
    public static function forId(int $producerId): self
    {
        return new self((string) __('parties.producer_agreement.missing_producer', [
            'producer' => $producerId,
        ]));
    }
}
