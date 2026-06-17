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
 * KYC-CLEARED GATE (parties-compliance, design L5; Module K PRD § 4.4 / BR-K-Producer-2): activation
 * additionally requires the Producer's `kyc_status` to be CLEARED — `verified`, `not_required`, or NULL (a
 * Producer never touched by KYC, treated as cleared so the additive nullable field, DEC-071, never breaks the
 * activation of rows created before this change — ADR 2026-06-17). A `pending`/`rejected` `kyc_status` blocks:
 * the action throws {@see IllegalProducerTransition::kycNotCleared()} and the transaction rolls back, leaving the
 * Producer `draft` with no event recorded. This closes the seam the previously-shipped `parties-producer-lifecycle`
 * slice left ungated; the cleared semantics ride {@see ProducerActivated} (§ 15.4 — there is no separate KYC event).
 *
 * From-state guarded and race-safe (design L2): inside ONE {@see DB::transaction} it re-reads the row
 * `->lockForUpdate()` (a real row lock on PostgreSQL, a harmless no-op under SQLite's single writer — the
 * from-state assert carries correctness either way), asserts `status === draft` and that KYC is cleared, then
 * writes `active` and records the event. A call on a Producer not in `draft` throws
 * {@see IllegalProducerTransition::cannotActivate()}, and a `draft` Producer whose KYC is not cleared throws
 * {@see IllegalProducerTransition::kycNotCleared()}; either way the transaction rolls back, leaving the row and
 * the event log unchanged. The status write and the event
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

            // KYC-cleared gate (design L5; § 4.4 / BR-K-Producer-2): a Producer activates only with KYC
            // cleared — `verified`, `not_required`, or NULL (never touched, treated as cleared for additivity,
            // ADR 2026-06-17). `pending`/`rejected` block. The null-check narrows `$kyc` to a non-null blocking
            // KycStatus before the throw, so `kycNotCleared` receives the offending state.
            $kyc = $producer->kyc_status;
            if ($kyc !== null && ! $kyc->clears()) {
                throw IllegalProducerTransition::kycNotCleared($kyc);
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
