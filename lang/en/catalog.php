<?php

// Catalog (Module 0) operator-facing copy — domain-rejection reasons surfaced by the creation Actions
// (catalog-product-spine) and the lifecycle-transition Actions (catalog-lifecycle-approval; CLAUDE.md
// invariant 12 — no hardcoded user-facing strings).
//
// English is the authored baseline AND the final fallback (DEC-127): every key is defined here in full.
// The other five supported locales (lang/{it,fr,de,ja,zh_Hans}/catalog.php) MAY cover a subset and fall
// back here per key — Laravel resolves the chain [active-locale, en] for each key (AC-0-XM-4 allows partial
// coverage). Convention: PHP-array group files with dotted keys (__('catalog.product_master.…')), :name
// placeholders replaced from the call site. See docs/i18n.md.

return [
    'product_master' => [
        // BR-Identity-1 dedup rejection (design D6). :name / :appellation / :producer are operator-facing
        // identity (not PII).
        'duplicate_identity' => 'A WINE Product Master already exists for producer :producer with the name ":name" and appellation ":appellation". The identity key (producer + product name + appellation) must be unique.',
        // Fail-closed non-WINE rejection (design D2). :type is the rejected token.
        'unsupported_product_type' => 'Unsupported Product Type ":type". At launch the only supported Product Type is WINE.',
    ],
    'composite_sku' => [
        // N ≥ 2 rejection (design D9 / BR-SKU-2). :count is the number of distinct constituents provided.
        'insufficient_constituents' => 'A Composite SKU requires at least two distinct constituent Product References; :count was provided.',
    ],
    'lifecycle' => [
        // The uniform four-state FSM `draft → reviewed → active → retired` (+ the `retired → reviewed`
        // reopen) shared by every spine entity (design D1/D2; Module 0 PRD § 4.1). One parameterized
        // IllegalLifecycleTransition surfaces these on an out-of-state call (the FSM is identical across
        // entities, so the entity name is a parameter — unlike Module K's three distinct FSMs). :state is
        // the offending from-state token (a business enum value); :entity is the entity-type name (e.g.
        // ProductMaster) — NEITHER is PII. Each reason names the rule, the offending state and the entity.
        'cannot_submit' => 'Cannot submit this :entity for review from state :state. A :entity submits for review only from draft.',
        'cannot_activate' => 'Cannot activate this :entity from state :state. A :entity activates only from reviewed.',
        'cannot_retire' => 'Cannot retire this :entity from state :state. A :entity retires only from active.',
        'cannot_reopen' => 'Cannot reopen this :entity from state :state. A :entity reopens to reviewed only from retired.',
        // Rejection (§ 4.3) is a reviewed → reviewed decision (no state change), so it is valid only from
        // reviewed; an out-of-state reject surfaces this through the same single parameterized exception.
        'cannot_reject' => 'Cannot reject this :entity from state :state. A :entity may be rejected for review only while in reviewed.',
    ],
    'approval' => [
        // The Creator → Reviewer → Approver separation-of-duties floor on every commercial-impact transition
        // (design D5; Module 0 PRD § 4.2). The audit trail is the system of record for which actor performed
        // each step; these reasons surface a rejected governance step. :entity is the entity-type name (e.g.
        // ProductMaster) — NOT PII. The acting principal is never named in the copy (the audit row carries the
        // actor_role / actor_id); the reason names only the violated rule.
        'requires_operator' => 'An approval or rejection step on this :entity requires an authenticated operator principal; a system actor cannot satisfy the separation-of-duties floor.',
        'self_approval_creator' => 'Separation of duties on this :entity: its creator may not also approve it.',
        'self_approval_reviewer' => 'Separation of duties on this :entity: its reviewer may not also approve it.',
        'insufficient_separation' => 'Separation of duties on this :entity: the three-step approval requires three distinct operators (creator, reviewer, approver), but its creator and reviewer were the same operator.',
    ],
    'gate' => [
        // The Producer activation gate (design D6; Module 0 PRD § 5.4, BR-Producer-1). A Product Master may
        // reach `active` only while its linked Producer is `active` in Catalog's own producer-state
        // projection — a hard gate rejected at the workflow level; an absent or `retired` projection blocks
        // it. :entity is the entity-type name (e.g. ProductMaster) — NOT PII; the producer is referenced by
        // id only and never named in the copy (invariant 10, PII-free discipline).
        'producer_not_active' => 'Cannot activate this :entity: its linked Producer is not active. A Product Master may activate only while its linked Producer is active.',
        // The within-catalog activation cascade (design D7; Module 0 PRD § 4.4, BR-Lifecycle-3). A CHILD spine
        // entity may reach `active` only once every parent it depends on is `active` (Variant ← Master;
        // Reference ← Variant + Format; Sellable SKU ← Reference + Case Configuration; Composite SKU ← every
        // constituent Reference) — a hard gate rejected at the workflow level. :entity is the child's
        // entity-type name and :parent the parent it is waiting on (e.g. ProductVariant / ProductMaster) —
        // NEITHER is PII. The within-catalog sibling of producer_not_active (which gates the one cross-module
        // parent, a Master's Producer).
        'parent_not_active' => 'Cannot activate this :entity: its :parent is not active. A child entity may activate only once every entity it depends on is active (the activation cascade).',
    ],
    'retirement' => [
        // The within-catalog reference-integrity guard on a SINGLE-entity retire (design D8; Module 0 PRD
        // § 4.6, BR-Lifecycle-5 — the within-catalog subset; scoped to the terminal sellable edge per
        // decisions/2026-06-16-catalog-retirement-reference-integrity-scope.md, Option B). A Product Reference
        // referenced by an `active` Sellable / Composite SKU, or a Case Configuration referenced by an
        // `active` Sellable SKU, SHALL NOT be retired out from under the still-`active` terminal sellable
        // object — the open references are surfaced so the operator can close them, or retire the parent
        // together with its descendants via the operator-driven cascade. A hierarchy parent (a Master with
        // `active` Variants, a Variant with `active` PRs) is NOT blocked — its single-entity retire succeeds
        // and preserves its children (§ 4.5). :entity is the entity-type name and :references the surfaced open
        // referencers (entity-type + id tokens, e.g. "SellableSku#5, CompositeSku#9") — NEITHER is PII (the
        // cross-module downstream-reference leg — Allocations / vouchers / Offers — is a documented Phase-3 seam).
        'blocked_by_active_references' => 'Cannot retire this :entity while it is still referenced by active within-catalog sellable objects (:references). Close those references first, or retire the parent together with its descendants via the operator-driven cascade.',
    ],
];
