<?php

namespace App\Platform\Events;

use RuntimeException;

/**
 * Raised when a substrate recorder is invoked with no database transaction active
 * (foundations-domain-events-audit, design D3). Shared by the domain-event recorder (task 3.4) and
 * the audit recorder (task 3.3) — the two append paths enforce the same guard.
 *
 * The substrate is a transactional outbox: a domain event / audit record MUST be appended inside the
 * same transaction as the state change it records, so the two commit or roll back together (no
 * dual-write — CLAUDE.md invariant 4; event-substrate spec, Transactional Event Recording: "The
 * recorder SHALL refuse to record when no database transaction is active"). The guard
 * (`DB::transactionLevel() === 0`) makes that rule enforced, not merely advised — recording outside
 * a transaction fails loudly instead of silently forfeiting atomicity.
 *
 * Lives under `App\Platform\Events` (the substrate's core namespace, which already owns the recorder,
 * contract and registry); `App\Platform\Audit` reuses it the same way it already reuses
 * `App\Platform\Events\ActorRole`.
 */
class NotInTransactionException extends RuntimeException
{
    /**
     * @param  string  $what  the noun for the message, e.g. "an audit record" / "a domain event"
     */
    public static function forRecording(string $what): self
    {
        return new self(sprintf(
            'Cannot record %s outside a database transaction: the no-dual-write guarantee requires '
            .'an active transaction. Wrap the call in DB::transaction().',
            $what,
        ));
    }
}
