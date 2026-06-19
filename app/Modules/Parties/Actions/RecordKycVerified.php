<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Events\CustomerHoldLifted;
use App\Modules\Parties\Exceptions\IllegalKycTransition;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Hold;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Records a Customer's KYC as verified — transitions `kyc_status` `pending → verified` and AUTO-LIFTS the
 * Customer's active `kyc` Hold(s), atomically (parties-holds, design L2/L7; party-registry — MODIFIED
 * Requirement: Customer KYC Lifecycle).
 *
 * This action is the SOLE writer of the Customer `kyc_status` for the verify transition. `verified` is a
 * CLEARED (non-blocking) state ({@see KycStatus::clears()} — `verified` ∨ `not_required`); it is reachable only
 * from `pending` (§ 9.1). The KYC FSM is SEPARATE from the Customer status FSM: this transition NEVER moves
 * `Customer.status`.
 *
 * KYC itself records NO KYC domain event (design L3 — § 15.1 names none); the `kyc_status` change is audit-only.
 * But clearing KYC AUTO-LIFTS the Customer's active `kyc` Hold(s) (the coupling — design L2/L7): the `kyc` Hold's
 * blocking effect (§ 9.1) ends when identity is verified. This is the SYSTEM lift path — it deliberately does NOT
 * reuse {@see LiftHold}, whose per-type discipline REJECTS an auto-managed `kyc` Hold from the operator path
 * (`IllegalHoldLift::autoManaged()`): the system lifts on the clearing signal what an operator may not lift
 * by hand (DEC-160; ADR 2026-06-18-hold-lift-discipline-per-type). It injects the {@see DomainEventRecorder} and
 * resolves the actor from the {@see ActorContext} seam; one resolution + one lift moment stamp BOTH each Hold's
 * `lifted_actor_*`/`lifted_at` columns and its {@see CustomerHoldLifted} envelope, so a Hold row and its event
 * can never disagree. A system lift carries no free text → `lift_reason = null` (design L5). The lift(s) and their
 * events run in the SAME transaction as the verify write (the recorder's open-transaction guard makes them
 * atomic). `rejected` is the contrast — `RecordKycRejected` LEAVES the Hold in place (§ 9.1).
 *
 * THE RESTORE SIDE OF THE COUPLING (parties-membership-suspension task 4.2, design L6; party-registry — Requirement:
 * Hold-Driven Status Coupling; ADR 2026-06-19): a status-bearing scope is `suspended` IFF covered by ≥1 active Hold,
 * so after lifting the `kyc` Hold(s) this Action RESTORES the Customer by INVOKING {@see ReactivateCustomer} (which
 * cascade-restores its uncovered Profiles) in the SAME transaction — but ONLY when the Customer is currently
 * `suspended` AND no OTHER active Hold still covers it (re-queried coverage; the just-lifted `kyc` Hold(s) are already
 * `lifted`). This is a NO-OP at onboarding (the Customer is `pending`, never suspended); it goes live on a
 * post-activation re-screen, where `RequireKyc → PlaceHold` suspended an `active` Customer (the place coupling — task
 * 4.1) and this verify lifts the `kyc` Hold and restores it. Deliberately does NOT reuse {@see LiftHold} (the per-type
 * lift discipline forbids the operator path from a `kyc` Hold), so the system lift + restore are wired here too.
 *
 * From-state guarded and race-safe (design L2, mirroring `ActivateProducer`): inside ONE {@see DB::transaction}
 * it re-reads the row `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the from-state
 * assert carries correctness either way), asserts `kyc_status === pending`, then writes `verified` and lifts the
 * Hold(s). A call on a Customer not in `pending` — including a NULL `kyc_status` (an un-screened Customer;
 * DEC-071) — throws {@see IllegalKycTransition::cannotVerify()} BEFORE any write, and the transaction rolls back
 * leaving the row, the Hold(s) and the event log unchanged. `version` is NOT bumped (it is reserved for
 * identity-attribute revisions — parties-core). The Models stay persistence-only; this action is the
 * `kyc_status` writer and the system `kyc`-Hold lift-writer (design L2).
 */
class RecordKycVerified
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
        private readonly ReactivateCustomer $reactivateCustomer,
    ) {}

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

            // The coupling (design L2/L7): system-lift every active Customer-scope `kyc` Hold in the SAME
            // transaction. One actor resolution + one lift moment stamp both each Hold row and its event envelope
            // (the seam resolves lazily per call — design L8). BR-K-Hold-1 permits multiple concurrent Holds, so this
            // lifts all active `kyc` Hold(s) for the scope (in practice the one the require placed). A system lift
            // carries no free text → lift_reason null (design L5).
            $actorRole = $this->actor->role();
            $actorId = $this->actor->actorId();
            $liftedAt = CarbonImmutable::now();

            $holds = Hold::query()
                ->where('scope_type', HoldScope::Customer->value)
                ->where('scope_id', $customer->id)
                ->where('hold_type', HoldType::Kyc->value)
                ->where('status', HoldStatus::Active->value)
                ->lockForUpdate()
                ->get();

            foreach ($holds as $hold) {
                $hold->update([
                    'status' => HoldStatus::Lifted,
                    'lifted_actor_role' => $actorRole,
                    'lifted_actor_id' => $actorId,
                    'lifted_at' => $liftedAt,
                    'lift_reason' => null,
                ]);

                // Record CustomerHoldLifted in the SAME transaction (the recorder's open-transaction guard makes
                // write + emit atomic). The event class is the single source of truth for the name / entity type /
                // PII-free payload (which reads the just-written `lift_reason`). Root event (no causation/correlation).
                $this->recorder->record(
                    name: CustomerHoldLifted::NAME,
                    module: Module::Parties->value,
                    actorRole: $actorRole,
                    actorId: $actorId,
                    entityType: CustomerHoldLifted::ENTITY_TYPE,
                    entityId: (string) $hold->id,
                    payload: CustomerHoldLifted::payload($hold),
                );
            }

            // The restore side of the Hold→`suspended` coupling (parties-membership-suspension task 4.2, design L6;
            // ADR 2026-06-19): after lifting the `kyc` Hold(s), restore the Customer IFF it is now `suspended` and no
            // OTHER active Hold still covers it (ReactivateCustomer cascade-restores its uncovered Profiles). A no-op at
            // onboarding (the Customer was `pending`, never suspended); live on a post-activation re-screen, where the
            // `kyc` Hold suspended an `active` Customer (the place coupling) and this lift restores it. The nested
            // ReactivateCustomer transaction is a SAVEPOINT under this verify transaction.
            $this->restoreCustomerIfUncovered($customer->id);

            return $customer;
        });
    }

    /**
     * Restores the Customer ({@see ReactivateCustomer}, cascade-restoring its uncovered Profiles) after the system
     * `kyc`-lift IFF it is currently `suspended` AND no OTHER active Customer-scope Hold still covers it (the
     * just-lifted `kyc` Hold(s) are already `lifted`, so the coverage re-query excludes them). The from-state pre-check
     * keeps this from throwing when the Customer was never suspended (onboarding KYC on a `pending` Customer — the
     * canonical no-op; the live path is a post-activation re-screen). The Reactivate Action is the sole status writer +
     * event emitter; this only decides WHETHER to invoke it. Deliberately does NOT reuse {@see LiftHold} (the per-type
     * lift discipline forbids the operator path from a `kyc` Hold), so the restore is wired here too — design L6.
     */
    private function restoreCustomerIfUncovered(int $customerId): void
    {
        $customer = Customer::query()->whereKey($customerId)->first();

        if ($customer?->status === CustomerStatus::Suspended && ! $this->customerStillCovered($customerId)) {
            $this->reactivateCustomer->handle($customerId);
        }
    }

    /**
     * Is the Customer still covered by another active Customer-scope Hold? A Customer is suspended ONLY via a
     * Customer-scope Hold (Profile-scope/Account-scope Holds isolate — BR-K-Hold-4), so its coverage is exactly the
     * active Customer-scope Holds on it. Re-read as Hold ROWS `->lockForUpdate()->get()` + `count() > 0` (this path
     * writes, so it locks the coverage rows) — the proven idiom (NOT `->exists()`, which would lift `FOR UPDATE` into
     * an EXISTS subquery — avoided for PG cross-engine safety).
     */
    private function customerStillCovered(int $customerId): bool
    {
        $holds = Hold::query()
            ->where('status', HoldStatus::Active->value)
            ->where('scope_type', HoldScope::Customer->value)
            ->where('scope_id', $customerId)
            ->lockForUpdate()
            ->get();

        return count($holds) > 0;
    }
}
