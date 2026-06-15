<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\ProducerAgreementStatus;
use App\Modules\Parties\Events\ProducerAgreementTerminated;
use App\Modules\Parties\Exceptions\IllegalProducerAgreementTransition;
use App\Modules\Parties\Models\ProducerAgreement;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a ProducerAgreement `active → terminated` and records its {@see ProducerAgreementTerminated} event
 * atomically (parties-producer-lifecycle, design L1/L2/L4; party-registry — Requirement: ProducerAgreement
 * Lifecycle).
 *
 * This action is the SOLE writer of `ProducerAgreement.status` for the termination transition and the SINGLE
 * writer of the {@see ProducerAgreementTerminated} event. Termination is the terminal branch of the agreement
 * FSM `draft → active → superseded | terminated`: it is reachable only from `active`, so a `draft`, `superseded`
 * or `terminated` agreement cannot be terminated. Unlike activation, which generates a derived
 * `ProducerAgreementSuperseded` (it is a supersession SOURCE — see {@see ActivateProducerAgreement}), termination
 * is a pure standalone operator action — never a cascade target or source — so its `ProducerAgreementTerminated`
 * is always a root event, hence the simpler signature with no causation/correlation threading parameters.
 *
 * NO PRODUCER CASCADE (§ 4.6.1): terminating an agreement does NOT change the Producer's state — the Producer FSM
 * `draft → active → retired` is independent of its agreements (it is Producer retirement that cascades, sunsetting
 * the Producer's Clubs — never the reverse). This action writes only the one agreement's `status`; the Producer
 * row is left untouched. This is a deliberate domain fact, not a deferred seam.
 *
 * From-state guarded and race-safe (design L2): inside ONE {@see DB::transaction} it re-reads the row
 * `->lockForUpdate()` (a real row lock on PostgreSQL, a harmless no-op under SQLite's single writer — the
 * from-state assert carries correctness either way), asserts `status === active`, then writes `terminated` and
 * records the event. A call on an agreement not in `active` throws
 * {@see IllegalProducerAgreementTransition::cannotTerminate()} and the transaction rolls back, leaving the row
 * and the event log unchanged. The status write and the event are recorded in the same transaction (the
 * recorder's open-transaction guard makes write + emit atomic), and the payload reflects the POST-transition
 * state. `version` is NOT bumped — it is reserved for identity-attribute revisions (its parties-core meaning),
 * and the immutable domain event is the audit record of the transition (design L3). The Model stays
 * persistence-only; this action is the only state writer (design L1). The actor is resolved from the
 * {@see ActorContext} seam (System until real principals wire in — design L9).
 */
class TerminateProducerAgreement
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(int $agreementId): ProducerAgreement
    {
        return DB::transaction(function () use ($agreementId): ProducerAgreement {
            // Transaction-locked re-read so two concurrent termination attempts serialize on PostgreSQL; the
            // from-state assert below is the correctness guarantee (the lock is a no-op on SQLite).
            $agreement = ProducerAgreement::query()->whereKey($agreementId)->lockForUpdate()->firstOrFail();

            if ($agreement->status !== ProducerAgreementStatus::Active) {
                throw IllegalProducerAgreementTransition::cannotTerminate($agreement->status);
            }

            $agreement->update(['status' => ProducerAgreementStatus::Terminated]);

            // No causation/correlation passed → the recorder makes this a root event (its `correlation_id`
            // defaults to its own `event_id`): termination is never part of a cascade and drives no derived event.
            $this->recorder->record(
                name: ProducerAgreementTerminated::NAME,
                module: Module::Parties->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: ProducerAgreementTerminated::ENTITY_TYPE,
                entityId: (string) $agreement->id,
                payload: ProducerAgreementTerminated::payload($agreement),
            );

            return $agreement;
        });
    }
}
