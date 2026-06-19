<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Events\CustomerHoldPlaced;
use App\Modules\Parties\Models\Account;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Hold;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Places one Hold on a scope — NewCo's unified, trigger-agnostic account-restriction primitive (parties-holds,
 * design L3; party-registry — Requirements: Hold Lifecycle and Lift Discipline, Hold Events, Hold Registry,
 * Hold-Driven Status Coupling). It creates an `active` {@see Hold} carrying the {@see HoldType}, the polymorphic
 * scope (`scope_type` + `scope_id`), the optional business `reason` and the placement actor/moment, records the
 * verbatim {@see CustomerHoldPlaced} event, and drives the Hold→`suspended` coupling — all in the SAME transaction.
 *
 * This is the SOLE writer of a placed `parties_holds` row (the model stays persistence-only — design L3). It is
 * both the MANUAL operator-placement path (AC-K-MVP-2 — the registry is trigger-agnostic, every type placeable by
 * hand) AND the path `RequireKyc` reuses to auto-place the `kyc` Hold when KYC enters `pending` (one Action calling
 * another, the `RetireProducer → SunsetClub` precedent). The scope is a within-module reference with NO DB FK
 * (design L1); within-module referential integrity for the three-way polymorphic scope is the caller's (the one
 * live caller, `RequireKyc`, places on a Customer it has just transaction-locked).
 *
 * Like the other EVENTED Parties Actions (`RecordCustomerScreening`, `ActivateProducer`), it injects the
 * {@see DomainEventRecorder} and resolves the acting principal from the {@see ActorContext} seam (`ActorRole::System`
 * until real principals wire in; an authenticated operator → `ActorRole::NewcoOps` — design L8). One resolution
 * stamps BOTH the `placed_actor_role`/`placed_actor_id` columns and the event envelope, so the Hold row and its
 * event can never disagree on who placed it.
 *
 * Inside ONE {@see DB::transaction}: it creates the Hold (`status = active`), records `CustomerHoldPlaced` — the
 * recorder's open-transaction guard makes write + emit atomic (a rolled-back placement records nothing); no
 * causation/correlation is passed → the recorder makes this a ROOT event (a placement is operator- or
 * KYC-initiated, never a cascade step itself) — and THEN drives the Hold→`suspended` coupling.
 *
 * THE HOLD→`suspended` COUPLING (parties-membership-suspension task 4.1, design L6; party-registry — Requirement:
 * Hold-Driven Status Coupling; ADR 2026-06-19): a status-bearing scope is `suspended` IFF covered by ≥1 active Hold.
 * After appending the Hold, this Action drives the covered scope to `suspended` IFF it is currently in its
 * SUSPENDABLE FROM-STATE — by INVOKING the matching explicit Action in the SAME transaction (`customer ⇒`
 * {@see SuspendCustomer}, which cascades to the Customer's `Active` Profiles; `account ⇒` {@see SuspendAccount};
 * `profile ⇒` {@see SuspendProfile}). It does NOT duplicate the status write — the Suspend Action is the sole writer
 * of its status field and the emitter of its event (the operator-manual suspension path shares it — AC-K-BR-Customer-1
 * "explicit (manual or via Hold)"). The from-state is PRE-CHECKED here so the coupling never throws on a
 * legitimately-unaffected scope: a Hold whose scope is NOT in its suspendable from-state — the canonical case is the
 * `kyc` Hold auto-placed on a `pending` Customer at onboarding, plus any Hold on an `Applied` Profile or an
 * already-`suspended`/`closed` scope — records the Hold and drives NO transition, keeping the status FSM independent
 * of the KYC/sanctions FSMs (the shipped `ComplianceIndependenceTest`). The nested Suspend transaction is a SAVEPOINT
 * under this placement transaction, so the Hold + the suspension commit or roll back together (the
 * `RequireKyc → PlaceHold` nesting precedent, one level deeper — design L6 risk note; verified on PostgreSQL 17).
 *
 * `LiftHold` is the lifting counterpart (its restore side of the coupling lands at task 4.2); the per-type lift
 * discipline lives on `HoldType::autoLiftable()`.
 */
class PlaceHold
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
        private readonly SuspendCustomer $suspendCustomer,
        private readonly SuspendAccount $suspendAccount,
        private readonly SuspendProfile $suspendProfile,
    ) {}

    public function handle(HoldType $type, HoldScope $scope, int $scopeId, ?string $reason = null): Hold
    {
        return DB::transaction(function () use ($type, $scope, $scopeId, $reason): Hold {
            // One actor resolution stamps both the Hold row and the event envelope, so the placed_actor_* columns
            // and the CustomerHoldPlaced envelope can never disagree (the seam resolves lazily per call — design L8).
            $actorRole = $this->actor->role();
            $actorId = $this->actor->actorId();

            // The Action is the sole writer (design L3); `$guarded = []` carries no request-mass-assignment risk —
            // the attributes are assembled here, never from input. Born `active` (HoldStatus::Active set explicitly
            // so the returned model reads back `active` in-memory, not relying on the column default).
            $hold = Hold::create([
                'hold_type' => $type,
                'scope_type' => $scope,
                'scope_id' => $scopeId,
                'status' => HoldStatus::Active,
                'reason' => $reason,
                'placed_actor_role' => $actorRole,
                'placed_actor_id' => $actorId,
            ]);

            // Record CustomerHoldPlaced in the SAME transaction (the recorder's open-transaction guard makes write +
            // emit atomic). The event class is the single source of truth for the name / entity type / PII-free
            // payload, so this Action stays thin and magic-string-free.
            $this->recorder->record(
                name: CustomerHoldPlaced::NAME,
                module: Module::Parties->value,
                actorRole: $actorRole,
                actorId: $actorId,
                entityType: CustomerHoldPlaced::ENTITY_TYPE,
                entityId: (string) $hold->id,
                payload: CustomerHoldPlaced::payload($hold),
            );

            // The Hold→`suspended` coupling (design L6; ADR 2026-06-19): in the SAME transaction, drive the covered
            // scope to `suspended` IFF it is in its suspendable from-state (pre-checked so a Hold on a non-suspendable
            // scope — e.g. the onboarding `kyc` Hold on a `pending` Customer — records the Hold and transitions nothing).
            $this->coupleSuspension($scope, $scopeId);

            return $hold;
        });
    }

    /**
     * Drives the just-held scope to `suspended` by invoking the matching explicit Suspend Action, IFF the scope is
     * currently in its suspendable from-state (design L6). The Suspend Action is the sole status writer + event
     * emitter; this only decides WHETHER to invoke it (the from-state pre-check that keeps the coupling from throwing
     * on a non-suspendable scope, and the status FSM independent of the KYC/sanctions FSMs).
     */
    private function coupleSuspension(HoldScope $scope, int $scopeId): void
    {
        match ($scope) {
            HoldScope::Customer => $this->suspendCustomerIfActive($scopeId),
            HoldScope::Account => $this->suspendAccountIfActive($scopeId),
            HoldScope::Profile => $this->suspendProfileIfActive($scopeId),
        };
    }

    /**
     * A Customer-scope Hold suspends the Customer (cascading to its `Active` Profiles — {@see SuspendCustomer}) only
     * from `active`; a `pending`/`suspended`/`closed` (or absent) Customer records the Hold and transitions nothing.
     */
    private function suspendCustomerIfActive(int $customerId): void
    {
        $customer = Customer::query()->whereKey($customerId)->first();

        if ($customer?->status === CustomerStatus::Active) {
            $this->suspendCustomer->handle($customerId);
        }
    }

    /**
     * An Account-scope Hold suspends the Account ({@see SuspendAccount}, audit-only) only from `active`; Account-scope
     * Holds isolate to the Account (BR-K-Hold-4) — no Customer/Profile cascade.
     */
    private function suspendAccountIfActive(int $accountId): void
    {
        $account = Account::query()->whereKey($accountId)->first();

        if ($account?->status === AccountStatus::Active) {
            $this->suspendAccount->handle($accountId);
        }
    }

    /**
     * A Profile-scope Hold suspends the Profile ({@see SuspendProfile}) only from `Active`; Profile-scope Holds
     * isolate (BR-K-Hold-4) — the owning Customer is untouched.
     */
    private function suspendProfileIfActive(int $profileId): void
    {
        $profile = Profile::query()->whereKey($profileId)->first();

        if ($profile?->state === ProfileState::Active) {
            $this->suspendProfile->handle($profileId);
        }
    }
}
