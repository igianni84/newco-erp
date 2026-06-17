<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Exceptions\IllegalKycTransition;
use App\Modules\Parties\Models\Producer;
use Illuminate\Support\Facades\DB;

/**
 * Waives KYC on a Producer — the operator "deselect" that transitions `kyc_status` from any outstanding state →
 * `not_required` atomically (parties-compliance, design L2/L3; party-registry — Requirement: Producer KYC
 * Lifecycle; ADR 2026-06-17-producer-kyc-gate-not-required-clears).
 *
 * Producer-ONLY (the Customer side has no waive). `not_required` is a CLEARED state EQUIVALENT to `verified` at
 * every gate (§ 4.4 — "`not_required` and `verified` are equivalent at every gate"), so waiving lets the Producer
 * activate and be used downstream exactly as if verified — the operator act Paolo described ("il KYC è un attributo
 * che l'operatore può banalmente deselezionare su un producer"). It is reachable from any OUTSTANDING state:
 * `pending`, `rejected`, `verified` (a re-deselect), and NULL (never-screened → explicitly `not_required`). The ONE
 * illegal case is waiving when ALREADY `not_required` — there is nothing to deselect — which throws
 * {@see IllegalKycTransition::cannotWaive()} (a non-null factory: NULL is a legal from-state, so the guard never
 * reaches the throw with a NULL).
 *
 * This action is the SOLE writer of the Producer `kyc_status` for the waive transition. KYC records NO domain event
 * (design L3 — § 15.1/§ 15.4 names none), so — unlike `ActivateProducer`/`SunsetClub` — it needs no recorder/actor
 * dependency; NO Hold is touched (scope guard). The Producer `status` (`draft`) is NEVER moved: Producer KYC is a
 * SEPARATE FSM from the Producer status FSM.
 *
 * From-state guarded and race-safe (design L2, mirroring `ActivateProducer`): inside ONE {@see DB::transaction} it
 * re-reads the row `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the from-state assert
 * carries correctness either way), asserts the Producer is not already `not_required`, then writes `not_required`.
 * A waive on an already-`not_required` Producer throws and the transaction rolls back, leaving the row unchanged.
 * `version` is NOT bumped (parties-core meaning). The Model stays persistence-only; this action is the only writer.
 */
class WaiveProducerKyc
{
    public function handle(int $producerId): Producer
    {
        return DB::transaction(function () use ($producerId): Producer {
            // Transaction-locked re-read so two concurrent waive attempts serialize on PostgreSQL; the
            // from-state assert below is the correctness guarantee (the lock is a no-op on SQLite).
            $producer = Producer::query()->whereKey($producerId)->lockForUpdate()->firstOrFail();

            $from = $producer->kyc_status;

            // The deselect applies only to an OUTSTANDING requirement; already-`not_required` has nothing to waive.
            // NULL (never-screened) is a legal from-state → explicitly recorded as `not_required` (no throw).
            if ($from === KycStatus::NotRequired) {
                throw IllegalKycTransition::cannotWaive($from);
            }

            $producer->update(['kyc_status' => KycStatus::NotRequired]);

            return $producer;
        });
    }
}
