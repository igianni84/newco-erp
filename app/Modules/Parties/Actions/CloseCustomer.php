<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Events\CustomerClosed;
use App\Modules\Parties\Exceptions\IllegalCustomerTransition;
use App\Modules\Parties\Models\Customer;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a Customer `active | suspended → closed` and records its {@see CustomerClosed} event atomically —
 * the terminal demand-side Customer FSM step (parties-membership-suspension, design L4/L7/L11; party-registry —
 * Requirements: Customer Suspension and Closure, Demand-Side Status Events).
 *
 * This Action is the SOLE writer of `Customer.status` for the closure transition and the SINGLE writer of the ROOT
 * {@see CustomerClosed} event. `closed` is reachable from `active` (a live Customer) OR `suspended` (a held one) —
 * the two cancellable from-states (§ 4.1); the inverse {@see ReactivateCustomer} restores `suspended → active`, and
 * {@see SuspendCustomer} drives `active → suspended` (cascading). There is NO transition OUT of `closed` — it is
 * terminal.
 *
 * NO PROFILE CASCADE (design L7 — § 15.1): unlike {@see SuspendCustomer} (whose `CustomerSuspended` "cascades to all
 * the Customer's Profiles"), § 15.1 `CustomerClosed` names NO cascade. Zero-invention leaves Profile resolution at
 * closure to the spec's silence — closure writes ONLY `Customer.status` and records ONLY the root `CustomerClosed`;
 * it transitions no Profile (inventing a destructive Profile cascade is forbidden). A future change refines this if
 * the spec gains a closure rule.
 *
 * TERMINAL, NOT ANONYMISED (§ 4.1 / AC-K-BR-Customer-2): `closed` is a status — it is ORTHOGONAL to anonymisation.
 * A `closed` Customer stays admin-queryable until separately anonymised; PII erasure is a deferred
 * `parties-anonymisation` seam (the `CustomerClosed` payload is PII-free either way — it carries only the id + the
 * `closed` status). So `CustomerClosed` is always a ROOT event with no causation children.
 *
 * EXPLICIT — NEVER AUTO-DRIVEN (design L7; § 9.4; AC-K-BR-Customer-1): closure is explicit (operator). Like
 * suspension it is NEVER auto-fired by a Profile state change or a KYC/sanctions verdict — the Customer status FSM
 * is separate from and independent of the compliance FSMs (§ 9.4; the shipped `ComplianceIndependenceTest` pins a
 * screening verdict on a `pending` Customer never flips `status`).
 *
 * From-state guarded and race-safe (design L4, mirroring {@see ActivateCustomer} / {@see SuspendCustomer}): inside
 * ONE {@see DB::transaction} it re-reads the Customer `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op
 * under SQLite — the from-state assert carries correctness either way), asserts `status ∈ {active, suspended}`, then
 * writes `closed` and records the root. A call on a Customer not in `active`/`suspended` (i.e. `pending` or already
 * `closed`) throws {@see IllegalCustomerTransition::cannotClose()} BEFORE any write, and the transaction rolls back
 * leaving the Customer and the event log unchanged. The payload reflects the POST-transition `status`. `version` is
 * NOT bumped (parties-core identity-revision semantics; the immutable domain event is the audit record of the
 * transition). The Model stays persistence-only; this Action is the sole status writer. The actor is resolved from
 * the {@see ActorContext} seam (System until real principals wire in).
 */
class CloseCustomer
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(int $customerId): Customer
    {
        return DB::transaction(function () use ($customerId): Customer {
            // Transaction-locked re-read so two concurrent closure attempts serialize on PostgreSQL; the from-state
            // assert below is the correctness guarantee (the lock is a no-op on SQLite).
            $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();

            // Closure is reachable from `active` or `suspended` (§ 4.1); `pending` and the already-`closed` terminal
            // reject, before any write.
            if (! in_array($customer->status, [CustomerStatus::Active, CustomerStatus::Suspended], true)) {
                throw IllegalCustomerTransition::cannotClose($customer->status);
            }

            // Write ONLY `status` — closure names no Profile cascade (design L7 — § 15.1).
            $customer->update(['status' => CustomerStatus::Closed]);

            // A ROOT event (no causation/correlation passed → the recorder defaults its `correlation_id` to its own
            // `event_id`): closure has no parent and no cascade children. The event class is the single source of
            // truth for the name / entity type / PII-free payload.
            $this->recorder->record(
                name: CustomerClosed::NAME,
                module: Module::Parties->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: CustomerClosed::ENTITY_TYPE,
                entityId: (string) $customer->id,
                payload: CustomerClosed::payload($customer),
            );

            return $customer;
        });
    }
}
