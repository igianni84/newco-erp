<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Exceptions\IllegalAccountTransition;
use App\Modules\Parties\Models\Account;
use Illuminate\Support\Facades\DB;

/**
 * Transitions an Account `suspended → active` — the Account's only `→ active` edge (it has no `pending` birth) and
 * AUDIT-ONLY, recording NO domain event (parties-membership-suspension, design L4/L8/L10; party-registry —
 * Requirements: Account Status Lifecycle, Demand-Side Status Events).
 *
 * NOT `ActivateAccount` (design L8; AC-K-FSM-9): the Account is co-provisioned with its Customer and born `active`,
 * so there is no `pending → active` activation — the ONLY restore edge is `suspended → active` = `ReactivateAccount`.
 * `ActivateAccount` is never created and stays in the forbidden-Action guard list forever.
 *
 * AUDIT-ONLY (design L8; § 15): the PRD § 15 event catalog names NO Account-family event, so — exactly as
 * {@see CancelProfile} writes `state` and records no event — `ReactivateAccount` writes `status = active` and records
 * NO domain event (the append-only audit trail of this write IS the record; inventing an `AccountReactivated` event
 * is forbidden — zero-invention). Being audit-only it injects NEITHER {@see DomainEventRecorder} NOR
 * {@see ActorContext}.
 *
 * EXPLICIT OR HOLD-DRIVEN (design L6; § 4.7): in production `suspended → active` is driven by the LIFT of the
 * Account-level Hold that suspended it — but only when NO other active Hold still covers the Account (the
 * coverage-recompute, ADR 2026-06-19, wired into `LiftHold` at the coupling tasks 4.x); the Action is also directly
 * operator-invocable.
 *
 * From-state guarded and race-safe (design L4, mirroring {@see CancelProfile}): inside ONE {@see DB::transaction} it
 * re-reads the Account `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the from-state
 * assert carries correctness either way), asserts `status === suspended`, then writes `active`. A call on an Account
 * not in `suspended` throws {@see IllegalAccountTransition::cannotReactivate()} BEFORE any write, and the transaction
 * rolls back leaving the Account unchanged. `version` is NOT bumped (parties-core identity-revision semantics). The
 * Model stays persistence-only; this Action is the sole status writer.
 */
class ReactivateAccount
{
    public function handle(int $accountId): Account
    {
        return DB::transaction(function () use ($accountId): Account {
            // Transaction-locked re-read so two concurrent attempts serialize on PostgreSQL; the from-state assert
            // below is the correctness guarantee (the lock is a no-op on SQLite).
            $account = Account::query()->whereKey($accountId)->lockForUpdate()->firstOrFail();

            // Reactivation is reachable only from `suspended` (§ 4.7); every other status rejects, before any write.
            if ($account->status !== AccountStatus::Suspended) {
                throw IllegalAccountTransition::cannotReactivate($account->status);
            }

            // AUDIT-ONLY (design L8): write ONLY `status` — NO domain event is recorded (§ 15 names no Account event;
            // the append-only audit trail of this write IS the record).
            $account->update(['status' => AccountStatus::Active]);

            return $account;
        });
    }
}
