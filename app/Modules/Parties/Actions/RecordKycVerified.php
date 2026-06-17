<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Exceptions\IllegalKycTransition;
use App\Modules\Parties\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Records a Customer's KYC as verified — transitions `kyc_status` `pending → verified` atomically
 * (parties-compliance, design L2/L3; party-registry — Requirement: Customer KYC Lifecycle).
 *
 * This action is the SOLE writer of the Customer `kyc_status` for the verify transition. `verified` is a
 * CLEARED (non-blocking) state ({@see KycStatus::clears()} — `verified` ∨ `not_required`); it is reachable only
 * from `pending` (§ 9.1). The KYC FSM is SEPARATE from the Customer status FSM: this transition NEVER moves
 * `Customer.status`.
 *
 * KYC records NO domain event (design L3 — § 15.1 names none); the change is audit-only, observable via the
 * column itself. No {@see DomainEventRecorder} is touched (no recorder/actor dependency) and NO Hold is lifted —
 * the `kyc` Hold auto-lift is owned by the deferred `parties-holds` change (scope guard); this slice records the
 * KYC state only.
 *
 * From-state guarded and race-safe (design L2, mirroring `ActivateProducer`): inside ONE {@see DB::transaction}
 * it re-reads the row `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the from-state
 * assert carries correctness either way), asserts `kyc_status === pending`, then writes `verified`. A call on a
 * Customer not in `pending` — including a NULL `kyc_status` (an un-screened Customer; DEC-071) — throws
 * {@see IllegalKycTransition::cannotVerify()} and the transaction rolls back, leaving the row unchanged.
 * `version` is NOT bumped (it is reserved for identity-attribute revisions — parties-core). The Model stays
 * persistence-only; this action is the only state writer (design L2).
 */
class RecordKycVerified
{
    public function handle(int $customerId): Customer
    {
        return DB::transaction(function () use ($customerId): Customer {
            // Transaction-locked re-read so two concurrent verify attempts serialize on PostgreSQL; the
            // from-state assert below is the correctness guarantee (the lock is a no-op on SQLite).
            $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();

            // Verify is reachable only from `pending`; every other state — including NULL (un-screened) — rejects.
            if ($customer->kyc_status !== KycStatus::Pending) {
                throw IllegalKycTransition::cannotVerify($customer->kyc_status);
            }

            $customer->update(['kyc_status' => KycStatus::Verified]);

            return $customer;
        });
    }
}
