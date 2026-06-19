<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\CustomerHoldLifted;
use App\Modules\Parties\Exceptions\IllegalHoldLift;
use App\Modules\Parties\Models\Account;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Hold;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Lifts one `active` Hold via the operator path — the lifting counterpart of {@see PlaceHold} (parties-holds,
 * design L2/L3; parties-membership-suspension design L6; party-registry — Requirements: Hold Lifecycle and Lift
 * Discipline, Hold Events, Hold-Driven Status Coupling). It moves the {@see Hold} `active → lifted`, records the
 * lift actor/moment and the optional business `lift_reason`, records the verbatim {@see CustomerHoldLifted} event,
 * and drives the restore side of the Hold→`suspended` coupling — all in the SAME transaction.
 *
 * This is the SOLE operator lift-writer of a `parties_holds` row (the model stays persistence-only — design L3).
 * It enforces the per-type lift discipline (DEC-160 § 4.8.1; AC-K-FSM-11; ADR
 * 2026-06-18-hold-lift-discipline-per-type) which lives on {@see HoldType::autoLiftable()}:
 *
 * - an **auto-managed** type (`kyc` / `payment` — `autoLiftable()`) is REJECTED here with
 *   {@see IllegalHoldLift::autoManaged()}: those lift only on their system clearing signal, never by hand (the
 *   `kyc` auto-lift is the separate within-module path {@see RecordKycVerified} drives; the `payment`
 *   auto-lift is a deferred Module-E seam);
 * - `admin` / `fraud` / `compliance` / `credit` lift freely through this path;
 * - a Hold that is not `active` (already `lifted`) is REJECTED with {@see IllegalHoldLift::notActive()} — a Hold
 *   lifts once.
 *
 * The status guard runs BEFORE the type guard, so an already-`lifted` Hold reports `notActive` regardless of type
 * (an already-resolved Hold is a more fundamental rejection than the type discipline).
 *
 * Like the other EVENTED Parties Actions (`RecordCustomerScreening`, `PlaceHold`) it injects the
 * {@see DomainEventRecorder} and resolves the acting principal from the {@see ActorContext} seam (`ActorRole::System`
 * until real principals wire in; an authenticated operator → `ActorRole::NewcoOps` — design L8). The actor is
 * resolved ONCE (after the guards, on the lift path only) and stamps BOTH the `lifted_actor_role`/`lifted_actor_id`
 * columns and the event envelope, so the Hold row and its event can never disagree on who lifted it; one
 * {@see CarbonImmutable::now()} likewise stamps the `lifted_at` column and is the single lift moment.
 *
 * From-state guarded and race-safe (design L3, mirroring `ActivateProducer`): inside ONE {@see DB::transaction} it
 * re-reads the row `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the guards carry
 * correctness either way), runs the two guards, then writes `lifted`, records `CustomerHoldLifted` and drives the
 * restore coupling — the recorder's open-transaction guard makes write + emit atomic, so a rejected lift records
 * nothing and a rolled-back lift leaves state and the event log unchanged. No causation/correlation is passed to the
 * lift event → the recorder makes it a ROOT event (an operator lift is operator-initiated, never a cascade step); the
 * restore it triggers records its OWN root (`CustomerReactivated`/`ProfileReactivated`, with the cascade's children).
 *
 * THE HOLD→`suspended` RESTORE COUPLING (parties-membership-suspension task 4.2, design L6; party-registry —
 * Requirement: Hold-Driven Status Coupling; ADR 2026-06-19): a status-bearing scope is `suspended` IFF covered by ≥1
 * active Hold. After lifting the Hold (so the coverage re-query below already excludes it), this Action RESTORES the
 * covered scope by INVOKING the matching explicit Reactivate Action in the SAME transaction — `customer ⇒`
 * {@see ReactivateCustomer} (which cascade-restores the Customer's uncovered Profiles); `account ⇒`
 * {@see ReactivateAccount}; `profile ⇒` {@see ReactivateProfile} — but ONLY when the scope is currently `suspended`
 * AND **no other active Hold still covers it** (re-querying coverage — the `(scope) OR, for a Profile, its
 * Customer-scope` shape `DatabaseComplianceStatusReader` resolves at READ, re-read here as Hold ROWS under lock since
 * this path writes). BR-K-Hold-1 admits many concurrent Holds, so a scope whose other Holds remain stays `suspended`
 * (restore only when the LAST covering Hold is gone — § 10.1's "the triggering Hold" read as the last covering Hold).
 * The from-state is PRE-CHECKED so the coupling never throws on a legitimately-unaffected scope: lifting a Hold off a
 * scope that is not `suspended` (an operator Hold on an `active` Customer/Profile, or any Hold on a `pending` Customer
 * / `Applied` Profile that never suspended) records the lift and drives NO transition. The nested Reactivate
 * transaction is a SAVEPOINT under this lift transaction, so the lift + the restore commit or roll back together (the
 * `RequireKyc → PlaceHold` nesting precedent, one level deeper — verified on PostgreSQL 17).
 */
class LiftHold
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
        private readonly ReactivateCustomer $reactivateCustomer,
        private readonly ReactivateAccount $reactivateAccount,
        private readonly ReactivateProfile $reactivateProfile,
    ) {}

    public function handle(int $holdId, ?string $reason = null): Hold
    {
        return DB::transaction(function () use ($holdId, $reason): Hold {
            // Transaction-locked re-read so two concurrent lift attempts serialize on PostgreSQL; the guards below
            // are the correctness guarantee (the lock is a no-op on SQLite).
            $hold = Hold::query()->whereKey($holdId)->lockForUpdate()->firstOrFail();

            // A Hold lifts once: an already-`lifted` Hold is rejected before the type discipline is consulted (the
            // status guard is the more fundamental rejection — an out-of-state lift is illegal whatever the type).
            if ($hold->status !== HoldStatus::Active) {
                throw IllegalHoldLift::notActive($hold->status);
            }

            // Per-type lift discipline (design L2): the operator path refuses an auto-managed type (`kyc`/`payment`)
            // — those lift only on their system clearing signal (the `kyc` auto-lift is RecordKycVerified's separate
            // within-module path; the `payment` auto-lift is a deferred Module-E seam).
            if ($hold->hold_type->autoLiftable()) {
                throw IllegalHoldLift::autoManaged($hold->hold_type);
            }

            // One actor resolution + one lift moment stamp both the Hold row and the event envelope, so the
            // lifted_actor_* / lifted_at columns and the CustomerHoldLifted envelope can never disagree (the seam
            // resolves lazily per call — design L8). Resolved on the lift path only (after the guards reject).
            $actorRole = $this->actor->role();
            $actorId = $this->actor->actorId();

            $hold->update([
                'status' => HoldStatus::Lifted,
                'lifted_actor_role' => $actorRole,
                'lifted_actor_id' => $actorId,
                'lifted_at' => CarbonImmutable::now(),
                'lift_reason' => $reason,
            ]);

            // Record CustomerHoldLifted in the SAME transaction (the recorder's open-transaction guard makes write +
            // emit atomic). The event class is the single source of truth for the name / entity type / PII-free
            // payload (which carries the now-updated `lift_reason`), so this Action stays thin and magic-string-free.
            $this->recorder->record(
                name: CustomerHoldLifted::NAME,
                module: Module::Parties->value,
                actorRole: $actorRole,
                actorId: $actorId,
                entityType: CustomerHoldLifted::ENTITY_TYPE,
                entityId: (string) $hold->id,
                payload: CustomerHoldLifted::payload($hold),
            );

            // The restore side of the Hold→`suspended` coupling (design L6; ADR 2026-06-19): in the SAME transaction,
            // restore the just-uncovered scope IFF it is currently `suspended` AND no OTHER active Hold still covers it
            // (the just-lifted Hold is already `lifted` above, so the coverage re-query excludes it).
            $this->coupleRestoration($hold->scope_type, $hold->scope_id);

            return $hold;
        });
    }

    /**
     * Drives the just-lifted scope back to `active`/`Active` by invoking the matching explicit Reactivate Action, IFF
     * the scope is currently `suspended` AND no OTHER active Hold still covers it (design L6). The Reactivate Action is
     * the sole status writer + event emitter; this only decides WHETHER to invoke it (the from-state pre-check keeps it
     * from throwing on a not-`suspended` scope; the coverage re-query keeps a still-covered scope `suspended`).
     */
    private function coupleRestoration(HoldScope $scope, int $scopeId): void
    {
        match ($scope) {
            HoldScope::Customer => $this->restoreCustomerIfUncovered($scopeId),
            HoldScope::Account => $this->restoreAccountIfUncovered($scopeId),
            HoldScope::Profile => $this->restoreProfileIfUncovered($scopeId),
        };
    }

    /**
     * A Customer-scope Hold lift restores the Customer ({@see ReactivateCustomer}, cascade-restoring its uncovered
     * Profiles) only from `suspended` and only when NO other active Customer-scope Hold still covers it (a Customer is
     * suspended only via a Customer-scope Hold). A non-`suspended` (or absent) Customer, or one another Customer-scope
     * Hold still covers, records the lift and transitions nothing.
     */
    private function restoreCustomerIfUncovered(int $customerId): void
    {
        $customer = Customer::query()->whereKey($customerId)->first();

        if ($customer?->status === CustomerStatus::Suspended && ! $this->customerStillCovered($customerId)) {
            $this->reactivateCustomer->handle($customerId);
        }
    }

    /**
     * An Account-scope Hold lift restores the Account ({@see ReactivateAccount}, audit-only) only from `suspended` and
     * only when NO other active Account-scope Hold still covers it; Account-scope Holds isolate to the Account
     * (BR-K-Hold-4) — no Customer/Profile cascade either way.
     */
    private function restoreAccountIfUncovered(int $accountId): void
    {
        $account = Account::query()->whereKey($accountId)->first();

        if ($account?->status === AccountStatus::Suspended && ! $this->accountStillCovered($accountId)) {
            $this->reactivateAccount->handle($accountId);
        }
    }

    /**
     * A Profile-scope Hold lift restores the Profile ({@see ReactivateProfile}) only from `Suspended` and only when NO
     * other active Hold still covers it — its own Profile-scope Hold OR a cascading Customer-scope Hold on its owning
     * Customer (BR-K-Hold-3). A Profile still under its Customer's Hold stays `Suspended` (the multi-Hold partial lift).
     */
    private function restoreProfileIfUncovered(int $profileId): void
    {
        $profile = Profile::query()->whereKey($profileId)->first();

        if ($profile?->state === ProfileState::Suspended && ! $this->profileStillCovered($profile)) {
            $this->reactivateProfile->handle($profileId);
        }
    }

    /**
     * Is the Customer still covered by another active Customer-scope Hold? A Customer is suspended ONLY via a
     * Customer-scope Hold (Profile-scope and Account-scope Holds isolate — BR-K-Hold-4), so its coverage is exactly
     * the active Customer-scope Holds on it. Re-read as Hold ROWS `->lockForUpdate()->get()` (this path writes, so it
     * locks the coverage rows against a concurrent place/lift) and reduced with `count() > 0` — the proven
     * `ReactivateCustomer`/`RecordKycVerified` idiom (NOT `->exists()`, which would lift `FOR UPDATE` into an EXISTS
     * subquery — avoided for PG cross-engine safety).
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

    /**
     * Is the Account still covered by another active Account-scope Hold? Account-scope Holds isolate to the Account
     * (BR-K-Hold-4 — no cascade), so coverage is exactly the active Account-scope Holds on it. Same locked-row idiom.
     */
    private function accountStillCovered(int $accountId): bool
    {
        $holds = Hold::query()
            ->where('status', HoldStatus::Active->value)
            ->where('scope_type', HoldScope::Account->value)
            ->where('scope_id', $accountId)
            ->lockForUpdate()
            ->get();

        return count($holds) > 0;
    }

    /**
     * Is the Profile still covered by any active Hold? Coverage is the BR-K-Hold-3 cascade shape — an active Hold on
     * the Profile's OWN scope, OR an active Customer-scope Hold on its owning Customer (Profile-scope and Account-scope
     * Holds isolate — BR-K-Hold-4). Mirrors {@see ReactivateCustomer}'s `stillCovered` and the `DatabaseComplianceStatusReader`
     * `(Profile-scope) OR (its Customer-scope)` union, re-read as Hold ROWS `->lockForUpdate()` since this path writes.
     */
    private function profileStillCovered(Profile $profile): bool
    {
        $holds = Hold::query()
            ->where('status', HoldStatus::Active->value)
            ->where(function (Builder $query) use ($profile): void {
                $query->where(function (Builder $inner) use ($profile): void {
                    $inner->where('scope_type', HoldScope::Profile->value)
                        ->where('scope_id', $profile->id);
                })->orWhere(function (Builder $inner) use ($profile): void {
                    $inner->where('scope_type', HoldScope::Customer->value)
                        ->where('scope_id', $profile->customer_id);
                });
            })
            ->lockForUpdate()
            ->get();

        return count($holds) > 0;
    }
}
