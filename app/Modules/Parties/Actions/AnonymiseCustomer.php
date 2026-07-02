<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Contracts\PartyComplianceStatusReader;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Events\CustomerAnonymised;
use App\Modules\Parties\Events\CustomerReactivated;
use App\Modules\Parties\Exceptions\AnonymisationBlockedByComplianceHold;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Support\AnonymisedPlaceholders;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Executes the GDPR right-to-erasure on a Customer by OVERWRITING personal data IN PLACE — never deleting rows —
 * so the keyed transactional history (Profiles, and downstream Orders/Vouchers/Invoices as they land) survives
 * for the retention window, queryable only through the now-opaque anonymised identifier (parties-anonymisation,
 * design D1/D2/D4; party-registry — Requirements: Customer Anonymisation (Right-to-Erasure), Anonymisation Hold
 * Precedence, Customer Address; AC-K-J-9 / AC-K-J-9a / AC-K-FSM-16 / BR-K-Customer-2).
 *
 * ORTHOGONAL to the Customer status FSM (BR-K-Customer-2): this Action writes NO `status` and records NO status
 * event ({@see CustomerClosed} / {@see CustomerReactivated}). Anonymisation is a
 * flag+timestamp (`anonymised_at`), a boolean-derivable state (`anonymised_at IS NOT NULL`), NEVER a status value
 * — a Customer of ANY status (typically `closed`) MAY be anonymised and keeps its status. Contrast the sole
 * status writers ({@see SuspendCustomer} / {@see CloseCustomer}).
 *
 * HOLD-PRECEDENCE GATE — canon MVP-DEC-015 (design D2; ADR
 * decisions/2026-07-02-adopt-dec-015-anonymisation-hold-block-set.md): anonymisation is blocked IFF an active
 * `compliance` Hold covers the Customer, and NO other {@see HoldType} blocks. Coverage is read through the
 * within-module {@see PartyComplianceStatusReader} (never the `Hold` model — the no-model-leak boundary law); a
 * block throws {@see AnonymisationBlockedByComplianceHold} BEFORE any write, rolling the transaction back so the
 * Customer is left entirely un-anonymised. Keying on `compliance` ALONE makes the gate immune to the RM-04 6→8
 * Hold-count debt (only one type gates, count-independent). Sanctions are NOT wired here — they live in the
 * separate `sanctions_status` FSM; a sanctioned Customer requiring retention is gated by a `compliance` Hold.
 *
 * From-state guarded and race-safe (the {@see CloseCustomer}/{@see SuspendCustomer} precedent): inside ONE
 * {@see DB::transaction} it re-reads the Customer `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op
 * under SQLite) so two concurrent attempts serialize, then:
 *   (d) IDEMPOTENT no-op — an already-anonymised Customer (`anonymised_at` set) returns unchanged, recording
 *       nothing; checked FIRST, since a re-run on an already-erased Customer has no PII left to retain and is
 *       harmless regardless of any later Hold;
 *   (gate) the compliance Hold-precedence gate above;
 *   (a) overwrites the Customer's four PII columns (`name`/`email`/`phone`/`date_of_birth`) with the
 *       deterministic, id-derived, UNIQUE-email-safe placeholders from {@see AnonymisedPlaceholders}, and every
 *       scoped {@see Address}'s personal fields with its constant placeholders — the Addresses re-read under lock
 *       so the whole erasure is all-or-nothing (the {@see SuspendCustomer} child-cascade precedent), rows
 *       PRESERVED (overwrite-in-place, never deleted); and
 *   (b) stamps `anonymised_at = CarbonImmutable::now()` (the module timestamp convention — {@see LapseProfile}
 *       `lapsed_at` / {@see LiftHold} `lifted_at`); and
 *   (c) REDACTS the Customer's own audit trail — nulls the `before`/`after` PII snapshots of every
 *       `audit_records` row for this Customer via {@see AuditRecorder::redactEntity()}, the sole mutation the
 *       immutability triggers permit (design D6). The record skeletons survive (never deleted). Module K writes
 *       NO audit snapshots today (task-3.3 investigation), so this is a DOCUMENTED NO-OP in practice — the
 *       capability is wired so erasure stays correct the day a PII-bearing Customer snapshot lands.
 * `version` is NOT bumped (parties-core identity-revision semantics). The model stays persistence-only; this
 * Action is the sole writer of the anonymisation overwrite.
 *
 * RECORDS the PII-free {@see CustomerAnonymised} event (design D3, task 3.4): after the overwrite/stamp/redact legs,
 * within the SAME transaction, this Action records exactly ONE `CustomerAnonymised` carrying only the Customer id +
 * the persisted `anonymised_at` moment (no name/email/phone/date-of-birth/address — the 10-year event store holds no
 * PII). It is the erasure signal downstream consumers key on; a ROOT event (erasure has no parent transition). The
 * idempotent early-return records NO second event on a re-run. This is NOT a status event — anonymisation stays
 * ORTHOGONAL to the status FSM (above). The Action was registered in `SupplyLifecycleChainTest` as a non-`Create*`
 * transition Action when it landed (task 3.2); task 3.4 adds no new Action class, only the event this Action records.
 */
class AnonymiseCustomer
{
    public function __construct(
        private readonly PartyComplianceStatusReader $compliance,
        private readonly AuditRecorder $audit,
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(int $customerId): Customer
    {
        return DB::transaction(function () use ($customerId): Customer {
            // Transaction-locked re-read so two concurrent anonymisation attempts serialize on PostgreSQL (a
            // no-op lock under SQLite; the checks below carry correctness either way).
            $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();

            // (d) IDEMPOTENT no-op — an already-anonymised Customer changes nothing and records nothing. Checked
            // BEFORE the gate: a re-run on an already-erased Customer has no PII left to retain, so it is harmless
            // regardless of any Hold placed afterwards.
            if ($customer->anonymised_at !== null) {
                return $customer;
            }

            // (gate) Hold-precedence — canon MVP-DEC-015: block IFF an active `compliance` Hold covers the
            // Customer; NO other HoldType blocks (count-independent). Read coverage through the within-module
            // read-contract (never the `Hold` model). Throw BEFORE any write so the transaction rolls back and
            // the Customer is left entirely un-anonymised.
            $coverage = $this->compliance->forCustomer($customer->id);
            if (in_array(HoldType::Compliance, $coverage->activeHoldTypes, true)) {
                throw AnonymisationBlockedByComplianceHold::forCustomer($customer->id);
            }

            $placeholders = AnonymisedPlaceholders::for($customer->id);

            // (a + b) Overwrite the Customer PII with the deterministic id-derived placeholders and stamp
            // `anonymised_at`. Writes NO `status` and records NO status event — anonymisation is ORTHOGONAL to the
            // status FSM (BR-K-Customer-2).
            $customer->update([
                ...$placeholders->customerAttributes(),
                'anonymised_at' => CarbonImmutable::now(),
            ]);

            // (a) Overwrite every scoped Address's personal fields in the SAME transaction, re-read under lock so
            // the whole erasure is all-or-nothing. The rows are PRESERVED (overwrite-in-place, never deleted) —
            // the FK-linked history survives keyed to the now-anonymised Customer.
            $addresses = $customer->addresses()->lockForUpdate()->get();
            foreach ($addresses as $address) {
                $address->update($placeholders->addressAttributes());
            }

            // (c) Redact the Customer's OWN audit trail — null the `before`/`after` PII snapshots of every
            // `audit_records` row for this Customer, the sole mutation the immutability triggers permit (a
            // before/after-only UPDATE; migration 2026_06_12_000004). The record skeletons SURVIVE — never
            // deleted, never structurally altered — so the append-only trail holds no PII (invariant 8).
            // Scoped to `CustomerAnonymised::ENTITY_TYPE` (`'Customer'`) — the single envelope value the recorded
            // event AND every other Customer domain event carry, so the redaction scope and the event's entity_type
            // share one source. Investigation (task 3.3): Module K records NO audit snapshots today (no
            // AuditRecorder caller under app/Modules/Parties), so in practice this is a DOCUMENTED NO-OP (redacts 0
            // rows); the capability is wired here so erasure stays correct the day a PII-bearing Customer snapshot
            // lands (design D6; the event-substrate reserved redaction seam).
            $this->audit->redactEntity(CustomerAnonymised::ENTITY_TYPE, (string) $customer->id);

            // Record the PII-free CustomerAnonymised event (design D3) — the erasure signal — LAST, so it commits
            // atomically with the overwrite/stamp/redact legs (or rolls back with them). The payload is the Customer
            // id + the persisted `anonymised_at` moment ONLY (no name/email/phone/dob/address). A ROOT event (no
            // causation/correlation passed → the recorder self-correlates): erasure has no parent transition. The
            // idempotent early-return above means a re-run never reaches here, so no SECOND event is recorded. The
            // actor is resolved from the {@see ActorContext} seam (System until real principals wire in).
            $this->recorder->record(
                name: CustomerAnonymised::NAME,
                module: Module::Parties->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: CustomerAnonymised::ENTITY_TYPE,
                entityId: (string) $customer->id,
                payload: CustomerAnonymised::payload($customer),
            );

            return $customer;
        });
    }
}
