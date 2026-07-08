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
        // Post-creation type-edit rejection (BR-Identity-5 / canon DEC-023). Product Type is fixed at
        // creation; the remedy is retire + re-register. :id is the Product Master id (not PII).
        'immutable_product_type' => 'The Product Type of a Product Master is fixed at creation and cannot be changed (Product Master :id). Retire this Master and register a new one under the required Product Type.',
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
        // Re-submit (RM-06 / canon MVP-DEC-019) is the twin of reject: a reviewed → reviewed decision (no
        // state change) that re-arms review after a rejection, so it too is valid only from reviewed. An
        // out-of-state re-submit surfaces this through the same single parameterized IllegalLifecycleTransition.
        'cannot_resubmit' => 'Cannot re-submit this :entity for review from state :state. A :entity may be re-submitted for review only while in reviewed.',
        // Review-freshness block-gate (RM-06 / canon MVP-DEC-019, plus its edit leg). activate
        // (reviewed → active) is refused while the entity is REVIEW-STALE — its latest review-freshness-relevant
        // audit action is an un-remediated rejection, OR an identity edit that has not been re-reviewed. The
        // remedy is the same explicit re-submit; the two reasons name the two different FACTS. Thrown as
        // ApprovalGovernanceViolation (enforced in the approval-governance guard, so it surfaces through the
        // console kit's outcome path for free), but the reasons live in this lifecycle group because the rule
        // they name is a lifecycle-flow / review-freshness rule, not a separation-of-duties one. Only :entity
        // (the entity-type name — never PII); the offending state is always reviewed and the acting principal
        // lives on the audit row. `un-remediated` is the discriminating token of the REJECTION cause (it
        // appears in no other catalog reason), `edited` of the EDIT cause — tests pin the block on them.
        'activation_blocked_by_pending_rejection' => 'Cannot activate this :entity: its latest review decision is an un-remediated rejection. The :entity must be re-submitted for review before it can be activated (review freshness).',
        'activation_blocked_by_unreviewed_edit' => 'Cannot activate this :entity: its review-governed identity content was edited after the last review decision. The :entity must be re-submitted for review before it can be activated (review freshness).',
    ],
    'edit' => [
        // The content-edit state guard (design D2/D3; product-catalog — Requirement: In-Place Versioned
        // Identity Edits). An edit is NOT a lifecycle transition, so it carries its own guard: content is
        // editable in draft / reviewed / active, and rejected on a `retired` entity — whose remedy is the
        // `retired → reviewed` reopen. Asserted against the transaction-locked re-read, so a rejected edit
        // writes nothing. One parameterized IllegalContentEdit serves every edit surface (identity,
        // composition, enrichment, whitelist): :entity is the entity-type name (e.g. ProductMaster) and
        // :state the offending from-state token (a business enum value) — NEITHER is PII. The reason
        // deliberately avoids the word `edited`, the discriminating token of the lifecycle group's
        // activation_blocked_by_unreviewed_edit cause.
        'cannot_edit' => 'Cannot edit this :entity from state :state. A retired :entity must be reopened for review before its content can be changed.',
    ],
    'reference' => [
        // A catalog write named a within-module entity by id and that entity does not exist (design D6;
        // product-catalog — Requirement: Layer-1 Case-Configuration Whitelist). The referenced ids are
        // FK-backed, so the DATABASE would refuse the write anyway — as a driver error carrying a constraint
        // name and no operator-facing meaning. This reason is the DOMAIN rejection that replaces it, raised
        // inside the write's transaction before any row is touched (the FK stays as the structural backstop).
        // One parameterized UnknownCatalogReference serves every reference kind (Format, CaseConfiguration, …):
        // :entity is the entity-type name and :ids the OFFENDING subset — the ids that resolved to nothing,
        // never the whole input — so a single stale id in a set names itself. NEITHER is PII (an entity-type
        // label and surrogate keys). The reason deliberately avoids the word `edited`, the discriminating token
        // of the lifecycle group's activation_blocked_by_unreviewed_edit cause.
        'unknown_reference' => 'Cannot complete this catalog write: it references :entity ids that do not exist (:ids). Only existing entities may be referenced.',
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
        // The SAME cascade invariant re-asserted at EDIT time on an `active` Composite SKU (design D2;
        // product-catalog — Requirement: In-Place Versioned Identity Edits). Activation is not the only way a
        // child could come to reference a non-`active` parent: replacing an `active` Composite's constituent set
        // could too, and the activation gate never runs again. Hence a second reason for the same violated rule,
        // naming the EDIT the operator actually attempted rather than an activation they never asked for.
        // :entity / :parent are entity-type names — NEITHER is PII. The copy deliberately avoids the word
        // `edited`, the discriminating token of the lifecycle group's activation_blocked_by_unreviewed_edit cause.
        'parent_not_active_on_composition_edit' => 'Cannot change the composition of this :entity: it is active, and every constituent :parent of an active :entity must itself be active. An active :entity may never come to reference a non-active :parent.',
        // The Layer-1 case-configuration whitelist gate (design D6, risk R10; Module 0 PRD § 7.1 + § 4.5,
        // AC-0-J-13). A Sellable SKU may reach `active` only if its Case Configuration is admitted for its
        // (Product Variant, Format) pair — resolved through its Product Reference — and only when that pair
        // holds a NON-EMPTY whitelist: an empty pair is PERMISSIVE (absence admits, presence narrows), so this
        // reason never names a whitelist that does not exist. Consulted ONLY at activation — reducing a pair's
        // admitted set blocks the NEXT activation and never reaches an already-`active` SKU (§ 4.5's
        // retirement-cascade semantics) — which is why the copy speaks of activating, not of the SKU being
        // invalid. :entity is the child's entity-type name (SellableSku) — NOT PII; neither the Case
        // Configuration nor the pair is named by id (the whitelist and the audit row carry those). The copy
        // deliberately avoids `edited`, the discriminating token of the lifecycle group's
        // activation_blocked_by_unreviewed_edit cause, and `not active`, that of parent_not_active.
        'case_configuration_not_whitelisted' => 'Cannot activate this :entity: its Case Configuration is not admitted for its Product Variant in this Format. Add it to the pair\'s Layer-1 whitelist, or activate a :entity referencing an admitted Case Configuration.',
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
