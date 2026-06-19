<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Exceptions\IllegalAccountTransition;
use App\Modules\Parties\Models\Account;
use Illuminate\Support\Facades\DB;

/**
 * Transitions an Account `active | suspended → closed` — the terminal Account FSM step, AUDIT-ONLY, recording NO
 * domain event (parties-membership-suspension, design L4/L8/L10; party-registry — Requirements: Account Status
 * Lifecycle, Demand-Side Status Events).
 *
 * The Account FSM is `active → suspended → closed` (§ 4.7): closure is reachable from `active` (a live Account) OR
 * `suspended` (a held one). `closed` is terminal — there is NO transition out of it (no `ActivateAccount`; the only
 * `→ active` edge is the restore {@see ReactivateAccount} from `suspended`, design L8).
 *
 * AUDIT-ONLY (design L8; § 15): the PRD § 15 event catalog names NO Account-family event, so — exactly as
 * {@see CancelProfile} writes `state` and records no event — `CloseAccount` writes `status = closed` and records NO
 * domain event (the append-only audit trail of this write IS the record; inventing an `AccountClosed` event is
 * forbidden — zero-invention). Being audit-only it injects NEITHER {@see DomainEventRecorder} NOR {@see ActorContext}.
 *
 * From-state guarded and race-safe (design L4, mirroring {@see CancelProfile}): inside ONE {@see DB::transaction} it
 * re-reads the Account `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the from-state
 * assert carries correctness either way), asserts `status ∈ {active, suspended}`, then writes `closed`. A call on an
 * Account already `closed` throws {@see IllegalAccountTransition::cannotClose()} BEFORE any write, and the transaction
 * rolls back leaving the Account unchanged. `version` is NOT bumped (parties-core identity-revision semantics). The
 * Model stays persistence-only; this Action is the sole status writer.
 */
class CloseAccount
{
    public function handle(int $accountId): Account
    {
        return DB::transaction(function () use ($accountId): Account {
            // Transaction-locked re-read so two concurrent attempts serialize on PostgreSQL; the from-state assert
            // below is the correctness guarantee (the lock is a no-op on SQLite).
            $account = Account::query()->whereKey($accountId)->lockForUpdate()->firstOrFail();

            // Closure is reachable from `active` or `suspended` (§ 4.7); the already-`closed` terminal rejects, before
            // any write.
            if (! in_array($account->status, [AccountStatus::Active, AccountStatus::Suspended], true)) {
                throw IllegalAccountTransition::cannotClose($account->status);
            }

            // AUDIT-ONLY (design L8): write ONLY `status` — NO domain event is recorded (§ 15 names no Account event;
            // the append-only audit trail of this write IS the record).
            $account->update(['status' => AccountStatus::Closed]);

            return $account;
        });
    }
}
