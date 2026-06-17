<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Exceptions\IllegalKycTransition;
use App\Modules\Parties\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Requires KYC on a Customer — transitions `kyc_status` `not_required`/NULL → `pending` and raises the
 * administratively-set `kyc_required` flag, atomically (parties-compliance, design L2/L3; party-registry —
 * Requirement: Customer KYC Lifecycle).
 *
 * This action is the SOLE writer of the Customer `kyc_status` (and `kyc_required`) for the require transition.
 * The KYC FSM `not_required → pending → verified | rejected` is SEPARATE from the Customer status FSM (§ 9.1):
 * a KYC transition NEVER moves `Customer.status`. Setting `kyc_required` is the operator act that opens KYC
 * (§ 9.1 — "setting `kyc_required` transitions `not_required → pending`"); a NULL `kyc_status` (a Customer
 * created un-screened — DEC-071) is an equally-valid starting point and clears the same guard.
 *
 * KYC records NO domain event (design L3 — the PRD event catalog § 15.1 names none); the change is audit-only,
 * observable via the column itself (and, later, the operator-console audit trail). No {@see DomainEventRecorder}
 * is touched, so — unlike `ActivateProducer`/`SunsetClub` — this action needs no recorder/actor dependency. NO
 * Hold is placed: the `kyc` Hold auto-placement is owned by the deferred `parties-holds` change (scope guard).
 * Because KYC has no event, that future Hold coupling will be within-module Action orchestration (this action
 * calling the Hold place), exactly as `RetireProducer` calls `SunsetClub`; leaving this action the single state
 * writer keeps that a clean later addition.
 *
 * From-state guarded and race-safe (design L2, mirroring `ActivateProducer`): inside ONE {@see DB::transaction}
 * it re-reads the row `->lockForUpdate()` (a real row lock on PostgreSQL, a harmless no-op under SQLite's single
 * writer — the from-state assert carries correctness either way), asserts `kyc_status` is `not_required` or NULL,
 * then writes `pending` + `kyc_required = true`. A call on a Customer already in `pending`/`verified`/`rejected`
 * throws {@see IllegalKycTransition::cannotRequire()} and the transaction rolls back, leaving the row unchanged.
 * `version` is NOT bumped — it is reserved for identity-attribute revisions (its parties-core meaning); a KYC
 * transition is not one. The Model stays persistence-only; this action is the only state writer (design L2).
 */
class RequireKyc
{
    public function handle(int $customerId): Customer
    {
        return DB::transaction(function () use ($customerId): Customer {
            // Transaction-locked re-read so two concurrent require attempts serialize on PostgreSQL; the
            // from-state assert below is the correctness guarantee (the lock is a no-op on SQLite).
            $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();

            $from = $customer->kyc_status;

            // `not_required` and NULL (un-screened — DEC-071) are the only states KYC opens from.
            if ($from !== null && $from !== KycStatus::NotRequired) {
                throw IllegalKycTransition::cannotRequire($from);
            }

            $customer->update([
                'kyc_status' => KycStatus::Pending,
                'kyc_required' => true,
            ]);

            return $customer;
        });
    }
}
