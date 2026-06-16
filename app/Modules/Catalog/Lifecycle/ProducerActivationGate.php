<?php

namespace App\Modules\Catalog\Lifecycle;

use App\Modules\Catalog\Consumers\ProducerLifecycleProjector;
use App\Modules\Catalog\Enums\ProducerProjectionStatus;
use App\Modules\Catalog\Exceptions\ProducerActivationGateViolation;
use App\Modules\Catalog\Models\ProducerState;

/**
 * The Producer activation gate — the cross-module precondition on a Product Master's `reviewed → active`
 * transition (catalog-lifecycle-approval, design D3/D6; product-catalog — Requirement: Producer Activation
 * Gate; Module 0 PRD § 5.4, BR-Producer-1). The {@see ActivateProductMaster} action passes
 * {@see assertProducerActive()} as the activation gate closure to the shared {@see LifecycleTransition}
 * mechanism, which evaluates it after the approval-governance guard and BEFORE the state write, inside the
 * transition's transaction; a breach throws {@see ProducerActivationGateViolation} and the whole transition
 * rolls back, so the Master stays `reviewed` and records no `ProductMasterActivated`.
 *
 * BOUNDARY LAW (invariant 10): the gate answers "is producer X `active`?" WITHOUT querying Module K — it
 * reads ONLY the Catalog-owned producer-state projection ({@see ProducerState}), the read model the
 * {@see ProducerLifecycleProjector} maintains from the consumed
 * `ProducerActivated`/`ProducerRetired` events. No `App\Modules\Parties\*` type is imported or queried.
 *
 * Fail-closed (the spec's "not gated open"): a producer with NO projection row — never activated, or a
 * `draft` producer that has emitted no `ProducerActivated` (so it never reached this read model) — is
 * treated exactly like a `retired` producer: the gate rejects. Only a row whose `status` is precisely
 * `active` opens the gate. The read is intentionally lock-free (a read-time gate, design D3 risk note): an
 * activation decided while the producer was `active` is valid even if a `ProducerRetired` commits a moment
 * later (block-NEW, never cascade-retire an in-flight activation).
 */
class ProducerActivationGate
{
    /**
     * Assert that the producer linked to the activating entity is `active` in Catalog's projection, else
     * reject the activation.
     *
     * @param  int  $producerId  the activating Product Master's `producer_id` (a plain id into Module K)
     * @param  string  $entity  the canonical entity-type label (e.g. `ProductMaster`) for the rejection copy
     *
     * @throws ProducerActivationGateViolation when the producer has no projection row or is not `active`
     */
    public function assertProducerActive(int $producerId, string $entity): void
    {
        $state = ProducerState::query()->where('producer_id', $producerId)->first();

        if ($state === null || $state->status !== ProducerProjectionStatus::Active) {
            throw ProducerActivationGateViolation::producerNotActive($entity);
        }
    }
}
