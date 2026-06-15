<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\ProducerAgreementStatus;
use App\Modules\Parties\Events\ProducerAgreementActivated;
use App\Modules\Parties\Events\ProducerAgreementSuperseded;
use App\Modules\Parties\Exceptions\IllegalProducerAgreementTransition;
use App\Modules\Parties\Models\ProducerAgreement;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a ProducerAgreement `draft → active`, records its {@see ProducerAgreementActivated} event, and
 * ENFORCES BR-K-Agreement-1 (at most one active agreement per scope) by superseding any prior active in the
 * same scope — all atomically in one transaction (parties-producer-lifecycle, design L1/L2/L4/L5/L7;
 * party-registry — Requirements: ProducerAgreement Lifecycle, Supply-Side Lifecycle Events).
 *
 * This action is the SOLE writer of `ProducerAgreement.status` for both the activation and the supersession
 * transitions and the SINGLE writer of both the {@see ProducerAgreementActivated} and
 * {@see ProducerAgreementSuperseded} events. Activation is the first transition of the agreement FSM
 * `draft → active → superseded | terminated`: it is reachable only from `draft`. It is a standalone operator
 * action — never a cascade target — so it takes the simpler standalone signature `handle(int $agreementId)` (no
 * causation/correlation parameters): it GENERATES the linkage for its derived `ProducerAgreementSuperseded` from
 * the recorded activation rather than receiving it (the signature rule it shares with
 * `ActivateProducer`/`RetireProducer`/`CloseClub`).
 *
 * SUPERSESSION (design L5/L7 — BR-K-Agreement-1/3): the scope is the `(producer_id, club_id)` tuple, where a
 * NULL `club_id` denotes the DISTINCT Producer-wide scope (a Producer-wide agreement and a Club-narrowed
 * agreement MAY both be active — they occupy different scopes). Before activating, it looks up the prior active
 * in the SAME scope with a NULL-SAFE `club_id` predicate — `whereNull('club_id')` when activating a
 * Producer-wide agreement, `where('club_id', …)` otherwise — because `where('club_id', null)` would emit
 * `club_id = NULL` and never match (the PostgreSQL NULL-distinctness trap, design L7). If a prior active is
 * found it is transitioned `active → superseded` in the same transaction and its supersession is recorded INLINE
 * (no separate Action — this is the cascade/derived SOURCE), threading the activation event's `id` as
 * `causation_id` and its `correlation_id` so the renewal is one queryable thread (design L5). The payload pairs
 * old + new: the activation's `supersedes` references the superseded id (null when nothing was superseded), the
 * supersession's `superseded_by` references the superseding id.
 *
 * From-state guarded and race-safe (design L2): inside ONE {@see DB::transaction} it re-reads the target row
 * `->lockForUpdate()` (a real row lock on PostgreSQL, a harmless no-op under SQLite's single writer — the
 * from-state assert carries correctness either way), asserts `status === draft`, locks the prior active in
 * scope, then writes and records. A call on an agreement not in `draft` throws
 * {@see IllegalProducerAgreementTransition::cannotActivate()} and the transaction rolls back, leaving every row
 * and the event log unchanged. The status writes and the events are recorded in the same transaction, and each
 * payload reflects the POST-transition state (record AFTER update). `version` is NOT bumped — it is reserved for
 * identity-attribute revisions (its parties-core meaning); the supersession linkage lives in the event payload,
 * not a new column, and the immutable domain event is the audit record of the transition (design L3). The Model
 * stays persistence-only; this action is the only state writer (design L1). The actor is resolved from the
 * {@see ActorContext} seam (System until real principals wire in — design L9).
 */
class ActivateProducerAgreement
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(int $agreementId): ProducerAgreement
    {
        return DB::transaction(function () use ($agreementId): ProducerAgreement {
            // Transaction-locked re-read so two concurrent activations serialize on PostgreSQL; the from-state
            // assert below is the correctness guarantee (the lock is a no-op on SQLite).
            $agreement = ProducerAgreement::query()->whereKey($agreementId)->lockForUpdate()->firstOrFail();

            if ($agreement->status !== ProducerAgreementStatus::Draft) {
                throw IllegalProducerAgreementTransition::cannotActivate($agreement->status);
            }

            // BR-K-Agreement-1: at most one active agreement per (producer_id, club_id) scope. Find (and lock)
            // the prior active in the SAME scope with a NULL-SAFE club_id predicate — `where('club_id', null)`
            // would emit `club_id = NULL` and never match (design L7). A NULL club_id is the distinct
            // Producer-wide scope, so a Producer-wide activation supersedes only other Producer-wide actives,
            // and a Club-narrowed activation supersedes only the prior active for that same Club.
            $priorQuery = ProducerAgreement::query()
                ->where('producer_id', $agreement->producer_id)
                ->where('status', ProducerAgreementStatus::Active->value)
                ->lockForUpdate();

            if ($agreement->club_id === null) {
                $priorQuery->whereNull('club_id');
            } else {
                $priorQuery->where('club_id', $agreement->club_id);
            }

            $prior = $priorQuery->first();

            // Supersede the prior active (if any) BEFORE activating this one, so the scope is never momentarily
            // double-active.
            $prior?->update(['status' => ProducerAgreementStatus::Superseded]);

            $agreement->update(['status' => ProducerAgreementStatus::Active]);

            // Record the activation FIRST and capture it — it roots the supersession chain. With no prior it is a
            // root event (the recorder defaults `correlation_id` to its own `event_id`); `supersedes` pairs the
            // new agreement with the one it replaced (null when nothing was superseded).
            $activated = $this->recorder->record(
                name: ProducerAgreementActivated::NAME,
                module: Module::Parties->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: ProducerAgreementActivated::ENTITY_TYPE,
                entityId: (string) $agreement->id,
                payload: ProducerAgreementActivated::payload($agreement, $prior?->id),
            );

            // If a prior active was superseded, record the paired ProducerAgreementSuperseded INLINE, caused by
            // (and sharing the correlation of) the activation — the renewal is one queryable thread (design L5).
            if ($prior !== null) {
                $this->recorder->record(
                    name: ProducerAgreementSuperseded::NAME,
                    module: Module::Parties->value,
                    actorRole: $this->actor->role(),
                    actorId: $this->actor->actorId(),
                    entityType: ProducerAgreementSuperseded::ENTITY_TYPE,
                    entityId: (string) $prior->id,
                    payload: ProducerAgreementSuperseded::payload($prior, $agreement->id),
                    correlationId: $activated->correlation_id,
                    causationId: $activated->id,
                );
            }

            return $agreement;
        });
    }
}
