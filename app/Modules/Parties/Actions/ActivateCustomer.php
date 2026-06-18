<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Events\CustomerActivated;
use App\Modules\Parties\Exceptions\IllegalCustomerTransition;
use App\Modules\Parties\Models\Customer;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a Customer `pending → active` behind the composite onboarding gate and records its
 * {@see CustomerActivated} event atomically (parties-membership-activation, design L6/L8; party-registry —
 * Requirements: Customer Onboarding Activation, Demand-Side Activation Events).
 *
 * This Action is the SOLE writer of `Customer.status` for the activation transition and the SINGLE writer of the
 * {@see CustomerActivated} event. Activation is the one retained demand-side Customer FSM step (`pending → active`,
 * § 4.1); every other transition (`active → suspended | closed`) stays deferred. Like {@see ActivateProducer} /
 * {@see ActivateProfile} it has a § 15.1 event, so it injects the recorder + actor and records `CustomerActivated`
 * in the same transaction as the `status` write.
 *
 * COMPOSITE ONBOARDING GATE (design L6; § 4.1 / § 7.1 / AC-K-J-1 + AC-K-BR-Identity-3): activation is a HARD
 * conjunctive gate — the four onboarding gates plus the KYC-cleared rider — evaluated AFTER the from-state guard:
 *   - `email_verified_at` set ∧ `tc_accepted_at` set ∧ `privacy_accepted_at` set (the three acceptance moments —
 *     additive nullable timestamps written by the deferred registration surface or an operator; a NULL is unmet),
 *   - `sanctions_status === passed` (an un-screened NULL or any non-`passed` verdict blocks),
 *   - KYC cleared whenever `kyc_required`: `! kyc_required` short-circuits, else `kyc_status` must clear — reusing
 *     {@see KycStatus::clears()} (`verified` / `not_required`), with a NULL `kyc_status`
 *     treated as cleared (DEC-071 — Customers are creatable un-screened; the additive nullable field never blocks a
 *     Customer never touched by KYC). A gate-unmet call throws {@see IllegalCustomerTransition::gateNotMet()} (which
 *     names only the gate conditions — the acceptance values are PII and never interpolated) and the transaction
 *     rolls back, leaving `status = pending` and the event log unchanged.
 *
 * EXPLICIT — NEVER AUTO-DRIVEN (design L6; § 9.4; AC-K-BR-Customer-1): activation is an explicit operator /
 * registration-surface Action. It is NOT auto-fired from {@see RecordCustomerScreening} or any KYC Action — the
 * Customer status FSM is separate from and independent of the KYC and sanctions FSMs (§ 9.4), so recording a
 * sanctions verdict or a KYC transition NEVER flips `status` (the shipped `ComplianceIndependenceTest` "screening
 * performs no status transition" assertion pins this). The gate READS the compliance fields; it does not couple the
 * FSMs.
 *
 * NO ACCOUNT TRANSITION (§ 4.7; AC-K-FSM-9): Customer activation performs no Account transition — the Account is
 * born `active` (it has no `pending` state), so there is nothing to activate. This Action touches only the Customer
 * row.
 *
 * From-state guarded and race-safe (design L4, mirroring `ActivateProducer`/`ActivateProfile`): inside ONE
 * {@see DB::transaction} it re-reads the Customer `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under
 * SQLite — the from-state assert carries correctness either way), asserts `status === pending`, then the gate, then
 * writes `active` and records the event. A call on a Customer not in `pending` throws
 * {@see IllegalCustomerTransition::cannotActivate()} BEFORE the gate is even evaluated, and the transaction rolls
 * back leaving the Customer and the event log unchanged. The payload reflects the POST-transition `status`.
 * `version` is NOT bumped (parties-core identity-revision semantics; the immutable domain event is the audit record
 * of the transition). The Model stays persistence-only; this Action is the sole status writer. `CustomerActivated`
 * is a ROOT event — the transition records no parent in its transaction, so no causation/correlation is threaded.
 * The actor is resolved from the {@see ActorContext} seam (System until real principals wire in).
 */
class ActivateCustomer
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(int $customerId): Customer
    {
        return DB::transaction(function () use ($customerId): Customer {
            // Transaction-locked re-read so two concurrent activation attempts serialize on PostgreSQL; the
            // from-state assert below is the correctness guarantee (the lock is a no-op on SQLite).
            $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();

            // Activation is reachable only from `pending` (§ 4.1); every other status rejects. The from-state
            // guard fires BEFORE the gate, so a wrong-state call is `cannotActivate`, never `gateNotMet`.
            if ($customer->status !== CustomerStatus::Pending) {
                throw IllegalCustomerTransition::cannotActivate($customer->status);
            }

            // The composite onboarding gate (design L6; § 4.1 / AC-K-J-1 + AC-K-BR-Identity-3) — a hard
            // conjunction; any unmet condition blocks activation with a PII-free reason.
            if (! $this->onboardingGateClears($customer)) {
                throw IllegalCustomerTransition::gateNotMet();
            }

            $customer->update(['status' => CustomerStatus::Active]);

            // No causation/correlation passed → the recorder makes this a root event (its `correlation_id` defaults
            // to its own `event_id`): the activation records no parent event. The event class is the single source
            // of truth for the name / entity type / PII-free payload.
            $this->recorder->record(
                name: CustomerActivated::NAME,
                module: Module::Parties->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: CustomerActivated::ENTITY_TYPE,
                entityId: (string) $customer->id,
                payload: CustomerActivated::payload($customer),
            );

            return $customer;
        });
    }

    /**
     * The composite onboarding gate (design L6; § 4.1 / § 7.1 / AC-K-J-1 + AC-K-BR-Identity-3): the four hard
     * onboarding gates plus the KYC-cleared rider. Returns true only when every condition clears.
     *
     * The three acceptance moments must be set; the sanctions screening must have `passed`; and KYC must clear
     * whenever `kyc_required` — `! kyc_required` short-circuits the KYC arm, else `kyc_status?->clears() !== false`
     * admits `verified` / `not_required` AND a NULL `kyc_status` (an un-screened Customer, treated as cleared —
     * DEC-071), while `pending` / `rejected` block. The status FSM only READS these compliance fields here; it
     * never writes them (§ 9.4 independence).
     */
    private function onboardingGateClears(Customer $customer): bool
    {
        return $customer->email_verified_at !== null
            && $customer->tc_accepted_at !== null
            && $customer->privacy_accepted_at !== null
            && $customer->sanctions_status === SanctionsStatus::Passed
            && (! $customer->kyc_required || $customer->kyc_status?->clears() !== false);
    }
}
