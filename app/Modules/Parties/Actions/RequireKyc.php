<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Exceptions\IllegalKycTransition;
use App\Modules\Parties\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Requires KYC on a Customer — transitions `kyc_status` `not_required`/NULL → `pending`, raises the
 * administratively-set `kyc_required` flag, and AUTO-PLACES the Customer-scope `kyc` Hold, atomically
 * (parties-holds, design L7; party-registry — MODIFIED Requirement: Customer KYC Lifecycle).
 *
 * This action is the SOLE writer of the Customer `kyc_status` (and `kyc_required`) for the require transition.
 * The KYC FSM `not_required → pending → verified | rejected` is SEPARATE from the Customer status FSM (§ 9.1):
 * a KYC transition NEVER moves `Customer.status`. Setting `kyc_required` is the operator act that opens KYC
 * (§ 9.1 — "setting `kyc_required` transitions `not_required → pending`"); a NULL `kyc_status` (a Customer
 * created un-screened — DEC-071) is an equally-valid starting point and clears the same guard.
 *
 * KYC itself records NO KYC domain event (design L3 — the PRD § 15.1 names none); the `kyc_status` change is
 * audit-only. But the blocking effect on purchases is realized by the `kyc` HOLD (§ 9.1), not the `kyc_status`
 * column, so opening KYC auto-places a Customer-scope `kyc` Hold in the SAME transaction (the coupling — design
 * L7). It REUSES {@see PlaceHold} (one action calling another — the `RetireProducer → SunsetClub` precedent):
 * PlaceHold's nested {@see DB::transaction} is a savepoint, so the `kyc_status` write, the Hold placement and the
 * `CustomerHoldPlaced` event commit or roll back together. The Hold is system-placed with `reason = null` (design
 * L5 — the type IS the reason; keeps the i18n invariant clean). `kyc_status` stays this action's alone (the Model
 * stays persistence-only); the Hold row is PlaceHold's.
 *
 * From-state guarded and race-safe (design L2, mirroring `ActivateProducer`): inside ONE {@see DB::transaction}
 * it re-reads the row `->lockForUpdate()` (a real row lock on PostgreSQL, a harmless no-op under SQLite's single
 * writer — the from-state assert carries correctness either way), asserts `kyc_status` is `not_required` or NULL,
 * then writes `pending` + `kyc_required = true` and places the Hold. A call on a Customer already in
 * `pending`/`verified`/`rejected` throws {@see IllegalKycTransition::cannotRequire()} BEFORE any write, and the
 * transaction rolls back leaving the row unchanged and no Hold placed. `version` is NOT bumped — it is reserved
 * for identity-attribute revisions (its parties-core meaning); a KYC transition is not one. This action is the
 * only `kyc_status` writer (design L2).
 */
class RequireKyc
{
    public function __construct(
        private readonly PlaceHold $placeHold,
    ) {}

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

            // The coupling (design L7): auto-place the Customer-scope `kyc` Hold in the SAME transaction (PlaceHold's
            // nested transaction is a savepoint). System-placed → reason null (design L5). PlaceHold records the
            // CustomerHoldPlaced event; this action records none of its own (KYC is event-silent — design L3).
            $this->placeHold->handle(HoldType::Kyc, HoldScope::Customer, $customer->id);

            return $customer;
        });
    }
}
