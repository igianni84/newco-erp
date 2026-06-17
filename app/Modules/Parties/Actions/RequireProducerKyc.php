<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Exceptions\IllegalKycTransition;
use App\Modules\Parties\Models\Producer;
use Illuminate\Support\Facades\DB;

/**
 * Requires KYC on a Producer — transitions `kyc_status` `not_required`/NULL → `pending` atomically
 * (parties-compliance, design L2/L3; party-registry — Requirement: Producer KYC Lifecycle).
 *
 * This action is the SOLE writer of the Producer `kyc_status` for the require transition. Producer KYC is the
 * provenance-KYC four-state domain `not_required → pending → verified | rejected` (§ 4.4) — DISTINCT from
 * Customer KYC (§ 9.1), though it shares the same {@see KycStatus} enum and the same {@see IllegalKycTransition}
 * vocabulary. Unlike the Customer side, the Producer carries NO `kyc_required` flag (the migration added only
 * `kyc_status` to `parties_producers`), so requiring KYC is simply the move into `pending`. A NULL `kyc_status`
 * (a Producer never touched by KYC — DEC-071) is an equally-valid starting point and clears the same guard.
 *
 * KYC records NO domain event (design L3 — the PRD event catalog § 15.1/§ 15.4 names none for KYC; the cleared
 * semantics ride `ProducerActivated` when activation fires). So — unlike `ActivateProducer`/`SunsetClub` — this
 * action needs no recorder/actor dependency. NO Hold is placed (the `kyc` Hold coupling is the deferred
 * `parties-holds` change — scope guard). The Producer `status` (`draft`) is NEVER moved: Producer KYC is a
 * SEPARATE FSM from the Producer status FSM, exactly as on the Customer side.
 *
 * From-state guarded and race-safe (design L2, mirroring `ActivateProducer`): inside ONE {@see DB::transaction}
 * it re-reads the row `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the from-state
 * assert carries correctness either way), asserts `kyc_status` is `not_required` or NULL, then writes `pending`.
 * A call on a Producer already in `pending`/`verified`/`rejected` throws {@see IllegalKycTransition::cannotRequire()}
 * and the transaction rolls back, leaving the row unchanged. `version` is NOT bumped (it is reserved for
 * identity-attribute revisions — parties-core). The Model stays persistence-only; this action is the only writer.
 */
class RequireProducerKyc
{
    public function handle(int $producerId): Producer
    {
        return DB::transaction(function () use ($producerId): Producer {
            // Transaction-locked re-read so two concurrent require attempts serialize on PostgreSQL; the
            // from-state assert below is the correctness guarantee (the lock is a no-op on SQLite).
            $producer = Producer::query()->whereKey($producerId)->lockForUpdate()->firstOrFail();

            $from = $producer->kyc_status;

            // `not_required` and NULL (a Producer never touched by KYC — DEC-071) are the only states KYC opens from.
            if ($from !== null && $from !== KycStatus::NotRequired) {
                throw IllegalKycTransition::cannotRequire($from);
            }

            $producer->update(['kyc_status' => KycStatus::Pending]);

            return $producer;
        });
    }
}
