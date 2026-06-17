<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Exceptions\IllegalKycTransition;
use App\Modules\Parties\Models\Producer;
use Illuminate\Support\Facades\DB;

/**
 * Records a Producer's KYC as verified — transitions `kyc_status` `pending → verified` atomically
 * (parties-compliance, design L2/L3; party-registry — Requirement: Producer KYC Lifecycle).
 *
 * This action is the SOLE writer of the Producer `kyc_status` for the verify transition. `verified` is a CLEARED
 * (non-blocking) state ({@see KycStatus::clears()} — `verified` ∨ `not_required`) that lets the Producer pass the
 * activation gate (design L5; BR-K-Producer-2); it is reachable only from `pending` (§ 4.4). Producer KYC is a
 * SEPARATE FSM from the Producer status FSM — this transition NEVER moves `Producer.status` off `draft`.
 *
 * KYC records NO domain event (design L3 — § 15.1/§ 15.4 names none; the cleared semantics ride `ProducerActivated`
 * at activation). No {@see DomainEventRecorder} is touched (no recorder/actor dependency) and NO Hold is lifted —
 * the `kyc` Hold coupling is the deferred `parties-holds` change (scope guard); this slice records the KYC state only.
 *
 * From-state guarded and race-safe (design L2, mirroring `ActivateProducer`): inside ONE {@see DB::transaction}
 * it re-reads the row `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the from-state
 * assert carries correctness either way), asserts `kyc_status === pending`, then writes `verified`. A call on a
 * Producer not in `pending` — including a NULL `kyc_status` (a Producer never touched by KYC; DEC-071) — throws
 * {@see IllegalKycTransition::cannotVerify()} (the widened nullable factory) and the transaction rolls back,
 * leaving the row unchanged. `version` is NOT bumped (parties-core meaning). The Model stays persistence-only;
 * this action is the only state writer (design L2).
 */
class RecordProducerKycVerified
{
    public function handle(int $producerId): Producer
    {
        return DB::transaction(function () use ($producerId): Producer {
            // Transaction-locked re-read so two concurrent verify attempts serialize on PostgreSQL; the
            // from-state assert below is the correctness guarantee (the lock is a no-op on SQLite).
            $producer = Producer::query()->whereKey($producerId)->lockForUpdate()->firstOrFail();

            // Verify is reachable only from `pending`; every other state — including NULL (never-screened) — rejects.
            if ($producer->kyc_status !== KycStatus::Pending) {
                throw IllegalKycTransition::cannotVerify($producer->kyc_status);
            }

            $producer->update(['kyc_status' => KycStatus::Verified]);

            return $producer;
        });
    }
}
