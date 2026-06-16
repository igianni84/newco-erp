<?php

namespace App\Modules\Catalog\Exceptions;

use App\Modules\Catalog\Models\ProducerState;
use RuntimeException;

/**
 * Raised when a Product Master's `reviewed → active` transition is blocked by the Producer activation gate
 * (catalog-lifecycle-approval, design D6; product-catalog — Requirement: Producer Activation Gate; Module 0
 * PRD § 5.4, BR-Producer-1). A Product Master SHALL NOT reach `active` unless its linked Producer is `active`
 * in Catalog's own producer-state projection ({@see ProducerState}); the gate is
 * a HARD gate, rejected at the workflow level. The transition's transaction rolls back, so the Master stays
 * `reviewed` and no `ProductMasterActivated` event (nor its audit row) is recorded.
 *
 * The KYC conjunct of § 5.4 ("`active` AND KYC-verified") is satisfied transitively UPSTREAM: a Producer
 * cannot reach `active` without a clear KYC verdict once `parties-compliance` tightens `ActivateProducer`
 * (DEC-071) — so this gate stays "linked Producer is `active`" with no Module 0 change when KYC lands (a
 * documented seam, design D6).
 *
 * The copy (the `gate` group of `lang/en/catalog.php`; CLAUDE.md invariant 12 — no hardcoded user-facing
 * strings) names only the violated rule and the `:entity` type label — never the producer (referenced by id
 * only, the substrate's PII-free discipline; invariant 10). One named factory mirrors the house exception
 * style ({@see ApprovalGovernanceViolation}, {@see IllegalLifecycleTransition}); `(string)` coerces the
 * translator return (typed `mixed` by Larastan) to the RuntimeException message contract.
 */
class ProducerActivationGateViolation extends RuntimeException
{
    public static function producerNotActive(string $entity): self
    {
        return new self((string) __('catalog.gate.producer_not_active', ['entity' => $entity]));
    }
}
