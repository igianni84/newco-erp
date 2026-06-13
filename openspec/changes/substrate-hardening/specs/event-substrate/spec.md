## ADDED Requirements

### Requirement: Concurrent Delivery Safety

A delivery completed (`done`) by one runner SHALL never be re-invoked nor moved off `done` by a concurrent runner — the inline post-commit hook and the scheduled sweep both run the same delivery ledger and MAY contend for the same row. The attempt path SHALL, inside its delivery transaction, re-fetch the delivery row under a row-level lock and SHALL NOT invoke the consumer handler when the row is no longer `pending` (a sibling runner already won it). The failure-recording path SHALL be a conditional update guarded on `status = pending`, so a delivery a sibling completed `done` between the failed attempt and the failure write is never resurrected to `pending` or `failed`. This makes the existing "a delivery already `done` SHALL never re-execute" guarantee hold under concurrency, not only under the single-runner, terminal-by-query path.

_Source: decisions/2026-06-12-event-substrate-and-audit-store.md (Delivery semantics — exactly-once for DB effects; dead-letter in place) · the event-substrate "Inline Delivery and Scheduled Sweep" requirement ("a delivery already `done` SHALL never re-execute") · CLAUDE.md invariant 4 (financial immutability — terminal state never rewritten) · substrate-hardening audit 2026-06-13, finding C1._

#### Scenario: A completed delivery is not re-invoked by a concurrent attempt

- **WHEN** an attempt runs over a delivery that a sibling runner has already completed `done`
- **THEN** the consumer handler is not invoked again, and the row stays `done` with its `attempts` unchanged

#### Scenario: A late failure record never resurrects a completed delivery

- **WHEN** a failed attempt tries to record its failure after a sibling runner has already completed the same delivery `done`
- **THEN** the conditional (pending-guarded) update matches no row, and the delivery remains `done` — never flipped to `pending` or `failed`

### Requirement: Delivery Failure Observability

Delivery failures SHALL be observable in the application log. The delivery executor SHALL log a warning for each failed but still-retryable attempt (identifying the delivery and carrying the error), and SHALL log an error when an attempt exhausts the configured maximum and the delivery transitions to `failed` (dead-letter in place). The scheduled sweep SHALL emit a summary at the end of each run recording how many deliveries it ran and how many of those failed. This is the operability floor for the dead-letter-in-place decision until a dedicated operator retry surface lands (a later change).

_Source: decisions/2026-06-12-event-substrate-and-audit-store.md (dead-letter in place — "the operator retry surface is a later change", so visibility of dead-letters is the launch floor) · the event-substrate "Inline Delivery and Scheduled Sweep" requirement (dead-letter semantics) · substrate-hardening audit 2026-06-13, finding C3._

#### Scenario: A retryable failure is logged at warning

- **WHEN** a consumer handler fails on an attempt below the configured maximum
- **THEN** a warning is logged identifying the delivery (id and consumer) and the error message, and the delivery stays `pending` for retry

#### Scenario: Dead-lettering is logged at error

- **WHEN** a consumer handler fails on the attempt that reaches the configured maximum, so the delivery becomes `failed`
- **THEN** an error is logged recording the dead-letter transition for that delivery

#### Scenario: The sweep logs a run summary

- **WHEN** the `events:sweep` command finishes a run
- **THEN** it logs a summary line recording the count of deliveries run and the count that failed
