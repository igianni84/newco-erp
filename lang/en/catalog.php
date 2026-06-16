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
];
