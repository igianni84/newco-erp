<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Exceptions\IllegalAccountTransition;
use App\Modules\Parties\Models\Account;
use Illuminate\Support\Facades\DB;

/**
 * Transitions an Account `active → suspended` — AUDIT-ONLY, recording NO domain event (parties-membership-suspension,
 * design L4/L8/L10; party-registry — Requirements: Account Status Lifecycle, Demand-Side Status Events).
 *
 * AUDIT-ONLY (design L8; § 15): the PRD § 15 event catalog names NO Account-family event (the Account is event-silent
 * at creation too — it records no `AccountCreated`), so — exactly as {@see CancelProfile} writes `state` and records
 * no event (the audit-only precedent) — `SuspendAccount` writes `status = suspended` and records NO domain event. The
 * `status = suspended` write captured in the append-only audit trail IS the record; inventing an `AccountSuspended`
 * event is forbidden (zero-invention — the catalog leaves the Account event-silent, and the guard test pins
 * `AccountActivated` as never-existing — the same discipline forbids any Account event). Being audit-only it injects
 * NEITHER {@see DomainEventRecorder} NOR {@see ActorContext} — it records no envelope of its own.
 *
 * The Account FSM is `active → suspended → closed` (§ 4.7), parallel to the Customer status FSM but with NO `pending`
 * birth — the Account is co-provisioned `active`, so its only `→ active` edge is the restore
 * {@see ReactivateAccount} (`suspended → active`); there is NO `ActivateAccount` (AC-K-FSM-9; design L8). The terminal
 * step is {@see CloseAccount}.
 *
 * EXPLICIT OR HOLD-DRIVEN (design L6; § 4.7): in production `active → suspended` is driven by an Account-level Hold
 * (ADR 2026-06-19, wired into `PlaceHold` at the coupling tasks 4.x); the Action is also directly operator-invocable.
 *
 * From-state guarded and race-safe (design L4, mirroring {@see CancelProfile}): inside ONE {@see DB::transaction} it
 * re-reads the Account `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the from-state
 * assert carries correctness either way), asserts `status === active`, then writes `suspended`. A call on an Account
 * not in `active` throws {@see IllegalAccountTransition::cannotSuspend()} BEFORE any write, and the transaction rolls
 * back leaving the Account unchanged. `version` is NOT bumped (parties-core identity-revision semantics). The Model
 * stays persistence-only; this Action is the sole status writer.
 */
class SuspendAccount
{
    public function handle(int $accountId): Account
    {
        return DB::transaction(function () use ($accountId): Account {
            // Transaction-locked re-read so two concurrent attempts serialize on PostgreSQL; the from-state assert
            // below is the correctness guarantee (the lock is a no-op on SQLite).
            $account = Account::query()->whereKey($accountId)->lockForUpdate()->firstOrFail();

            // Suspension is reachable only from `active` (§ 4.7); every other status rejects, before any write.
            if ($account->status !== AccountStatus::Active) {
                throw IllegalAccountTransition::cannotSuspend($account->status);
            }

            // AUDIT-ONLY (design L8): write ONLY `status` — NO domain event is recorded (§ 15 names no Account event;
            // the append-only audit trail of this write IS the record).
            $account->update(['status' => AccountStatus::Suspended]);

            return $account;
        });
    }
}
