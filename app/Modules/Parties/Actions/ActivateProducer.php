<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Events\ProducerActivated;
use App\Modules\Parties\Exceptions\IllegalProducerTransition;
use App\Modules\Parties\Models\Producer;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a Producer `draft → active` and records its {@see ProducerActivated} event atomically
 * (parties-producer-lifecycle, design L1/L2/L4/L8; party-registry — Requirement: Producer Lifecycle).
 *
 * This action is the SOLE writer of `Producer.status` for the activation transition and the SINGLE writer of
 * the {@see ProducerActivated} event. Activation is the first step of the Producer FSM `draft → active →
 * retired`: it is reachable only from `draft`. It is a standalone operator action — never a cascade target in
 * this slice (the Producer-retirement cascade in `RetireProducer` sunsets the operated Clubs; nothing activates
 * a Producer as a derived step) — so a `ProducerActivated` is always a root event and the action needs no
 * causation/correlation threading parameters (the simpler signature it shares with `CloseClub`).
 *
 * DEFERRED SEAM (design L8): the PRD precondition that activation requires KYC verification (Module K PRD § 4.4)
 * is NOT enforced here. The KYC four-state lifecycle and its fields are owned by the future `parties-compliance`
 * change (DEC-071 — sanctions/KYC fields are nullable, added additively), so they do not exist in this slice;
 * activation therefore succeeds with no KYC verdict present, and `parties-compliance` SHALL tighten this
 * transition to gate on a verified verdict. This is the same "seam now, behaviour later" discipline the spine
 * used for `originating_club_id` and `CloseClub` uses for its all-members-gone gate.
 *
 * From-state guarded and race-safe (design L2): inside ONE {@see DB::transaction} it re-reads the row
 * `->lockForUpdate()` (a real row lock on PostgreSQL, a harmless no-op under SQLite's single writer — the
 * from-state assert carries correctness either way), asserts `status === draft`, then writes `active` and
 * records the event. A call on a Producer not in `draft` throws {@see IllegalProducerTransition::cannotActivate()}
 * and the transaction rolls back, leaving the row and the event log unchanged. The status write and the event
 * are recorded in the same transaction (the recorder's open-transaction guard makes write + emit atomic), and
 * the payload reflects the POST-transition state. `version` is NOT bumped — it is reserved for identity-attribute
 * revisions (its parties-core meaning), and the immutable domain event is the audit record of the transition
 * (design L3). The Model stays persistence-only; this action is the only state writer (design L1). The actor is
 * resolved from the {@see ActorContext} seam (System until real principals wire in — design L9).
 */
class ActivateProducer
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(int $producerId): Producer
    {
        return DB::transaction(function () use ($producerId): Producer {
            // Transaction-locked re-read so two concurrent activation attempts serialize on PostgreSQL; the
            // from-state assert below is the correctness guarantee (the lock is a no-op on SQLite).
            $producer = Producer::query()->whereKey($producerId)->lockForUpdate()->firstOrFail();

            if ($producer->status !== ProducerStatus::Draft) {
                throw IllegalProducerTransition::cannotActivate($producer->status);
            }

            $producer->update(['status' => ProducerStatus::Active]);

            // No causation/correlation passed → the recorder makes this a root event (its `correlation_id`
            // defaults to its own `event_id`): activation is never part of a cascade in this slice.
            $this->recorder->record(
                name: ProducerActivated::NAME,
                module: Module::Parties->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: ProducerActivated::ENTITY_TYPE,
                entityId: (string) $producer->id,
                payload: ProducerActivated::payload($producer),
            );

            return $producer;
        });
    }
}
