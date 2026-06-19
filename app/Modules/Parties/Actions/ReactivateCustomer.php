<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\CustomerReactivated;
use App\Modules\Parties\Events\ProfileReactivated;
use App\Modules\Parties\Exceptions\IllegalCustomerTransition;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Hold;
use App\Modules\Parties\Models\Profile;
use App\Modules\Parties\Reads\DatabaseComplianceStatusReader;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a Customer `suspended → active`, records its {@see CustomerReactivated} event, and CASCADE-RESTORES
 * every Profile still `Suspended` that is no longer covered by any active Hold — all atomically in one transaction
 * (parties-membership-suspension, design L4/L6/L7/L11; party-registry — Requirements: Customer Suspension and
 * Closure, Demand-Side Status Events). It is the inverse of {@see SuspendCustomer}.
 *
 * This Action is the SOLE writer of `Customer.status` for the restore transition and the SINGLE writer of the ROOT
 * {@see CustomerReactivated} event. Restore is reachable only from `suspended` (§ 4.1).
 *
 * HOLD COUPLING — DRIVEN IN PRODUCTION (design L6; ADR 2026-06-19; § 10.1): in production this transition is driven
 * by the Hold→`suspended` coupling on the lift of the LAST covering Customer-scope Hold — `LiftHold` (operator) and
 * the system `kyc`-lift in `RecordKycVerified` invoke it iff coverage shows no other active Hold still covers the
 * Customer (wired in the coupling tasks 4.x). The Action is also directly operator-invocable (manual restore).
 *
 * COVERAGE-GUARDED CASCADE RESTORE (design L6/L7/L11 — the subtlest path): after recording `CustomerReactivated` it
 * re-reads the Customer's Profiles `->lockForUpdate()` and, for each Profile currently in `Suspended`, restores it to
 * `Active` + records a {@see ProfileReactivated} (a CAUSATION CHILD of the `CustomerReactivated` root — design L11)
 * **iff** that Profile is no longer covered by any active Hold. "Covered" is recomputed from Hold rows (the
 * coverage-recompute model, not provenance — ADR 2026-06-19): an active Hold on the Profile's OWN scope, OR an
 * active Customer-scope Hold on its owning Customer (the BR-K-Hold-3 cascade shape — the same `(Profile-scope) OR
 * (its Customer-scope)` union {@see DatabaseComplianceStatusReader} resolves at READ, but
 * re-read here as Hold ROWS `->lockForUpdate()` since this path WRITES). A Profile retaining its own active Hold — or
 * under a Customer that retains another active Hold — stays `Suspended`. The cascade inlines the state-write + event
 * (it does NOT delegate to {@see ReactivateProfile}, which records a ROOT event — the cascade needs causation
 * children, design L11). The whole cascade runs INSIDE this Action's transaction — all-or-nothing.
 *
 * From-state guarded and race-safe (design L4, mirroring {@see ActivateCustomer}): inside ONE {@see DB::transaction}
 * it re-reads the Customer `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the from-state
 * assert carries correctness either way), asserts `status === suspended`, then writes `active`, records the root and
 * runs the coverage-guarded cascade. A call on a Customer not in `suspended` throws
 * {@see IllegalCustomerTransition::cannotReactivate()} BEFORE any write, and the transaction rolls back leaving the
 * Customer, its Profiles and the event log unchanged. The payload reflects the POST-transition `status`/`state`.
 * `version` is NOT bumped (parties-core identity-revision semantics; the immutable domain event is the audit record).
 * The Models stay persistence-only; this Action is the sole status/cascade-state writer. The actor is resolved once
 * from the {@see ActorContext} seam (System until real principals wire in) and stamps both the root and every child.
 */
class ReactivateCustomer
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(int $customerId): Customer
    {
        return DB::transaction(function () use ($customerId): Customer {
            // Transaction-locked re-read so two concurrent restore attempts serialize on PostgreSQL; the from-state
            // assert below is the correctness guarantee (the lock is a no-op on SQLite).
            $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();

            // Restore is reachable only from `suspended` (§ 4.1); every other status rejects, before any write.
            if ($customer->status !== CustomerStatus::Suspended) {
                throw IllegalCustomerTransition::cannotReactivate($customer->status);
            }

            $customer->update(['status' => CustomerStatus::Active]);

            // One actor resolution stamps the root and every cascade child (the seam resolves lazily per call).
            $actorRole = $this->actor->role();
            $actorId = $this->actor->actorId();

            // The restore is a ROOT event; its `id` + `correlation_id` thread the cascade below.
            $root = $this->recorder->record(
                name: CustomerReactivated::NAME,
                module: Module::Parties->value,
                actorRole: $actorRole,
                actorId: $actorId,
                entityType: CustomerReactivated::ENTITY_TYPE,
                entityId: (string) $customer->id,
                payload: CustomerReactivated::payload($customer),
            );

            // Coverage-guarded cascade restore (design L6/L7): restore each `Suspended` Profile no longer covered by
            // any active Hold, linking each `ProfileReactivated` to the restore root (causation child — design L11).
            // Re-read under lock inside this same transaction so the whole cascade is all-or-nothing.
            $suspendedProfiles = $customer->profiles()
                ->where('state', ProfileState::Suspended->value)
                ->lockForUpdate()
                ->get();

            foreach ($suspendedProfiles as $profile) {
                // A Profile retaining its own active Hold — or under a Customer that retains another active Hold —
                // stays `Suspended` (the coverage-recompute, BR-K-Hold-1 multi-Hold safe).
                if ($this->stillCovered($profile)) {
                    continue;
                }

                // State-preserving (design L9): write ONLY `state`.
                $profile->update(['state' => ProfileState::Active]);

                $this->recorder->record(
                    name: ProfileReactivated::NAME,
                    module: Module::Parties->value,
                    actorRole: $actorRole,
                    actorId: $actorId,
                    entityType: ProfileReactivated::ENTITY_TYPE,
                    entityId: (string) $profile->id,
                    payload: ProfileReactivated::payload($profile),
                    correlationId: $root->correlation_id,
                    causationId: $root->id,
                );
            }

            return $customer;
        });
    }

    /**
     * Is the Profile still covered by at least one active Hold? Coverage is the BR-K-Hold-3 cascade shape — an active
     * Hold on the Profile's OWN scope, OR an active Customer-scope Hold on its owning Customer (Profile-scope and
     * Account-scope Holds isolate — BR-K-Hold-4). Mirrors {@see DatabaseComplianceStatusReader}'s
     * `(Profile-scope) OR (its Customer-scope)` union, but re-reads the Hold ROWS `->lockForUpdate()` (this path
     * writes, so it locks the coverage rows against a concurrent place/lift rather than reading the type-only DTO).
     */
    private function stillCovered(Profile $profile): bool
    {
        $coveringHolds = Hold::query()
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

        // Any active covering Hold keeps the Profile `Suspended`. Counted (not `->isNotEmpty()`) so the lock idiom
        // stays the proven `->lockForUpdate()->get()` of `RecordKycVerified` — the row lock is load-bearing here, and
        // `->exists()` would lift the FOR UPDATE into an EXISTS subquery (avoided for cross-engine safety on PG).
        return count($coveringHolds) > 0;
    }
}
