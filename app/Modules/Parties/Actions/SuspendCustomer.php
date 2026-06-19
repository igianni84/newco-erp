<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\CustomerSuspended;
use App\Modules\Parties\Events\ProfileSuspended;
use App\Modules\Parties\Exceptions\IllegalCustomerTransition;
use App\Modules\Parties\Models\Customer;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a Customer `active → suspended`, records its {@see CustomerSuspended} event, and CASCADES the
 * suspension onto every Profile still `Active` — all atomically in one transaction (parties-membership-suspension,
 * design L4/L7/L9/L11; party-registry — Requirements: Customer Suspension and Closure, Demand-Side Status Events).
 *
 * This Action is the SOLE writer of `Customer.status` for the suspension transition and the SINGLE writer of the
 * ROOT {@see CustomerSuspended} event. Suspension is reachable only from `active` (§ 4.1); the inverse restore is
 * {@see ReactivateCustomer} (`suspended → active`), the terminal step is {@see CloseCustomer}.
 *
 * EXPLICIT — NEVER AUTO-DRIVEN (design L7; § 9.4; AC-K-BR-Customer-1): suspension is explicit — manual (operator)
 * or via the Hold→`suspended` coupling (a Customer-scope Hold — ADR 2026-06-19, wired in `PlaceHold` at the coupling
 * tasks 4.x). It is NEVER auto-fired by a Profile state change or a KYC/sanctions verdict — the Customer status FSM
 * is separate from and independent of the compliance FSMs (§ 9.4; the shipped `ComplianceIndependenceTest` pins this:
 * a screening verdict on a `pending` Customer never flips `status`).
 *
 * CASCADE (design L7/L11 — § 15.1 "Cascades to all the Customer's Profiles"): after recording `CustomerSuspended` it
 * re-reads the Customer's Profiles `->lockForUpdate()` and, for each Profile currently in `Active`, writes
 * `Suspended` + records a {@see ProfileSuspended} threading the `CustomerSuspended` event's `id` as `causationId` and
 * its `correlation_id` as `correlationId` — so every cascade `ProfileSuspended` is a CAUSATION CHILD of the
 * suspension (one queryable causal chain in the 10-year audit log — the `RetireProducer → SunsetClub` precedent).
 * The cascade inlines the state-write + event (it does NOT delegate to {@see SuspendProfile}, which records a ROOT
 * event — the cascade needs causation children, design L11; the same reason {@see RecordKycVerified} inlines its
 * Hold lift rather than reusing `LiftHold`). Non-`Active` Profiles are skipped: the FSM has only `Active → Suspended`
 * (an `Applied`/`Approved`/`Lapsed`/terminal Profile has no suspend edge), and the Customer-scope Hold already
 * blocks them logically via the read-API cascade (BR-K-Hold-3). The cascade runs INSIDE this Action's transaction,
 * so the Customer suspension + every cascade `ProfileSuspended` commit or roll back together — all-or-nothing.
 *
 * STATE-PRESERVING (design L9; § 10.1 / AC-K-FSM-2a): the suspension writes ONLY the `status`/`state` fields (and the
 * cascade) — it does NOT cancel vouchers, pending orders or allocation reservations, nor mutate any Club Credit
 * balance. Those entities live in Module S/B/E and are unbuilt, so the "Club Credit frozen while suspended" guarantee
 * is a deferred `club-credit` seam (the credit entity will read `state`/`status` when it exists): nothing destructive
 * happens here.
 *
 * From-state guarded and race-safe (design L4, mirroring {@see ActivateCustomer}): inside ONE {@see DB::transaction}
 * it re-reads the Customer `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the from-state
 * assert carries correctness either way), asserts `status === active`, then writes `suspended`, records the root and
 * runs the cascade. A call on a Customer not in `active` throws {@see IllegalCustomerTransition::cannotSuspend()}
 * BEFORE any write, and the transaction rolls back leaving the Customer, its Profiles and the event log unchanged.
 * The payload reflects the POST-transition `status`/`state`. `version` is NOT bumped (parties-core identity-revision
 * semantics; the immutable domain event is the audit record of the transition). The Models stay persistence-only;
 * this Action is the sole status/cascade-state writer. The actor is resolved once from the {@see ActorContext} seam
 * (System until real principals wire in) and stamps both the root and every cascade child.
 */
class SuspendCustomer
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(int $customerId): Customer
    {
        return DB::transaction(function () use ($customerId): Customer {
            // Transaction-locked re-read so two concurrent suspension attempts serialize on PostgreSQL; the
            // from-state assert below is the correctness guarantee (the lock is a no-op on SQLite).
            $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();

            // Suspension is reachable only from `active` (§ 4.1); every other status rejects, before any write.
            if ($customer->status !== CustomerStatus::Active) {
                throw IllegalCustomerTransition::cannotSuspend($customer->status);
            }

            $customer->update(['status' => CustomerStatus::Suspended]);

            // One actor resolution stamps the root and every cascade child (the seam resolves lazily per call).
            $actorRole = $this->actor->role();
            $actorId = $this->actor->actorId();

            // The suspension is a ROOT event (no causation/correlation passed → the recorder defaults its
            // `correlation_id` to its own `event_id`); its `id` + `correlation_id` thread the cascade below.
            $root = $this->recorder->record(
                name: CustomerSuspended::NAME,
                module: Module::Parties->value,
                actorRole: $actorRole,
                actorId: $actorId,
                entityType: CustomerSuspended::ENTITY_TYPE,
                entityId: (string) $customer->id,
                payload: CustomerSuspended::payload($customer),
            );

            // § 15.1 cascade: suspend every Profile still `Active`, linking each cascade `ProfileSuspended` to the
            // suspension root (causation child — design L11). Re-read under lock inside this same transaction so the
            // whole cascade is all-or-nothing. Non-`Active` Profiles have no suspend edge and are skipped.
            $activeProfiles = $customer->profiles()
                ->where('state', ProfileState::Active->value)
                ->lockForUpdate()
                ->get();

            foreach ($activeProfiles as $profile) {
                // State-preserving (design L9): write ONLY `state` — no voucher/order/reservation/Club Credit.
                $profile->update(['state' => ProfileState::Suspended]);

                $this->recorder->record(
                    name: ProfileSuspended::NAME,
                    module: Module::Parties->value,
                    actorRole: $actorRole,
                    actorId: $actorId,
                    entityType: ProfileSuspended::ENTITY_TYPE,
                    entityId: (string) $profile->id,
                    payload: ProfileSuspended::payload($profile),
                    correlationId: $root->correlation_id,
                    causationId: $root->id,
                );
            }

            return $customer;
        });
    }
}
