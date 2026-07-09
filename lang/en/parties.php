<?php

// Parties (Module K) operator-facing copy — domain-rejection reasons surfaced by the creation Actions
// (parties-core; CLAUDE.md invariant 12 — no hardcoded user-facing strings).
//
// English is the authored baseline AND the final fallback (DEC-127): every key is defined here in full.
// The other five supported locales (lang/{it,fr,de,ja,zh_Hans}/parties.php) MAY cover a subset and fall
// back here per key — Laravel resolves the chain [active-locale, en] for each key. Convention: PHP-array
// group files with dotted keys (__('parties.club.…')), :producer placeholders replaced from the call site.
// See docs/i18n.md.

return [
    'producer' => [
        // Producer FSM `draft → active → retired` (parties-producer-lifecycle, design L2; § 4.4)
        // illegal-transition reasons. :state is the offending from-state token (a business enum value, not PII).
        'cannot_activate' => 'Cannot activate this Producer from state :state. A Producer activates only from draft.',
        'cannot_retire' => 'Cannot retire this Producer from state :state. A Producer retires only from active.',
        // KYC-cleared activation gate (parties-compliance, design L5; § 4.4 / BR-K-Producer-2): a Producer
        // activates only with KYC cleared (verified ∨ not_required; a NULL kyc_status is treated as cleared).
        // :state is the offending blocking KYC token (pending | rejected) — a business enum value, not PII.
        'kyc_not_cleared' => 'Cannot activate this Producer while its KYC is :state. Activation requires KYC cleared — verified or not_required.',
        // Review-governed content lock (change parties-module-k-br-guards, design D9; party-registry — Requirement:
        // Producer Review-Governed Content Lock; BR-K-Producer-5 / canon MVP-DEC-022 — interim safety core). The
        // descriptive fields name/description/region/website are immutable while a Producer is `active`: a
        // model-level `updating` chokepoint (the RM-24 pattern) rejects any edit that dirties them, leaving content
        // unchanged. The full "edit re-arms Creator→Reviewer→Approver review" UX is deferred (no Producer `reviewed`
        // state / content-edit path today) — this lock is replaced when it lands. :producer is the operator-facing
        // id reference (not PII); the copy names the locked field-set and the rule, never customer data.
        'review_governed_content_locked' => 'Cannot edit the review-governed content (name, description, region, website) of Producer :producer while it is active. This content is immutable on an active Producer — a content edit would require a fresh review pass, which is not yet available.',
    ],
    'approval' => [
        // Producer separation-of-duties floor on ActivateProducer (change parties-producer-approval-sod, design
        // D1/D4; party-registry — Requirement: Producer Lifecycle; Module K PRD § 4.4 / AC-K-J-10; Admin Panel
        // PRD § 5.2). ProducerApprovalGovernance guards the `draft → active` transition at the spec-admissible
        // 2-step Creator → Approver depth: the activator SHALL be an authenticated newco_ops operator
        // (requires_operator_principal — a system/null actor is rejected, closing the "System actor accepted"
        // hole) AND SHALL differ from the Producer's creator (creator_may_not_approve — the ProducerCreated actor
        // read from domain_events). Mirrors lang/en/catalog.php's `approval` group MINUS the reviewer leg (the
        // Producer FSM is linear — no `reviewed` state, no reviewer source). :entity is the entity-type label
        // ('Producer') — NEVER PII; the acting principal lives on the event/audit row (the system of record for
        // who performed each step), so the copy names only the violated RULE, mirroring the Catalog discipline.
        'requires_operator_principal' => 'Activating this :entity requires an authenticated operator principal; a system actor cannot satisfy the separation-of-duties floor.',
        'creator_may_not_approve' => 'Separation of duties on this :entity: its creator may not also activate it.',
    ],
    'club' => [
        // BR-K-Club-1 rejection (design D3/D4): a Club requires exactly one EXISTING operating Producer.
        // :producer is the operator-facing producer-id reference (not PII).
        'missing_producer' => 'Cannot create a Club: no operating Producer exists for reference :producer. A Club requires exactly one existing operating Producer.',
        // Club FSM `active → sunset → closed` (parties-producer-lifecycle, design L2; § 4.3) illegal-transition
        // reasons. :state is the offending from-state token (a business enum value, not PII).
        'cannot_sunset' => 'Cannot sunset this Club from state :state. A Club sunsets only from active.',
        'cannot_close' => 'Cannot close this Club from state :state. A Club closes only from sunset.',
        // New-membership block at Profile creation (change parties-module-k-br-guards, design D3; party-registry —
        // Requirement: Profile — Multi-Profile Membership; BR-K-Club-3 / AC-K-FSM-6). A `CreateProfile` targeting a
        // `sunset` or `closed` Club is rejected (no Profile, no ProfileCreated) — enforcing the frozen "sunset
        // blocks new memberships" rule at the creation chokepoint. :club is the operator-facing id reference (not
        // PII); :state is the offending ClubStatus token (a business enum, not PII) — the cannot_sunset discipline.
        'not_accepting_memberships' => 'Cannot create a Profile in Club :club: the Club is :state and no longer accepts new memberships. New memberships are accepted only by an active Club.',
        // Registration-flow value-domain reject (change parties-module-k-br-guards, task 4.3; party-registry —
        // Requirement: Club Registration Flow and Onboarding Channel; BR-K-Club-6 / canon MVP-DEC-022). The
        // `open_registration` value is carried latent in the enum but is NOT selectable at launch — it would
        // bypass the mandatory producer-approval write (DEC-069). :flow is the offending registration-flow token
        // (a business enum value, not PII) — the invalid_settlement_cadence discipline.
        'registration_flow_not_selectable' => 'The :flow registration flow is not selectable at launch: it is carried latent and would bypass the mandatory producer approval. Select one of application_with_approval, invitation_only, or link_onboarding.',
    ],
    'producer_agreement' => [
        // § 4.6 rejection (design D3/D4): a ProducerAgreement references exactly one EXISTING Producer.
        // :producer is the operator-facing producer-id reference (not PII).
        'missing_producer' => 'Cannot create a ProducerAgreement: no Producer exists for reference :producer. A ProducerAgreement requires exactly one existing Producer.',
        // ProducerAgreement FSM `draft → active → superseded | terminated` (parties-producer-lifecycle,
        // design L2; § 4.6.1) illegal-transition reasons. :state is the offending from-state token (not PII).
        'cannot_activate' => 'Cannot activate this ProducerAgreement from state :state. An agreement activates only from draft.',
        'cannot_terminate' => 'Cannot terminate this ProducerAgreement from state :state. An agreement terminates only from active.',
        // Per-Club scope requires an active Club (change parties-module-k-br-guards, design D5; party-registry —
        // Requirement: ProducerAgreement; BR-K-Agreement-4 / canon MVP-DEC-009). A new agreement scoped to a
        // `sunset`/`closed` Club is rejected; Producer-wide (club_id NULL) is ungated and supersession inherits
        // scope (exempt). :club is the operator-facing id reference (not PII); :state is the offending ClubStatus
        // token (a business enum, not PII).
        'club_not_active' => 'Cannot scope a ProducerAgreement to Club :club: the Club is :state, not active. A per-Club agreement requires an active Club — Producer-wide agreements are ungated.',
        // Cross-shape mutual exclusion at activation (change parties-module-k-br-guards, design D2; party-registry —
        // Requirement: ProducerAgreement Lifecycle; BR-K-Agreement-1 clause 2). A Producer's Producer-wide (club_id
        // NULL) and per-Club shapes SHALL NOT both be active — activating one while the other is active is rejected
        // (pre-write, state + event log unchanged); the operator terminates/supersedes the existing shape first.
        // Two direction-aware reasons. :producer is the operator-facing id reference (not PII); the copy names only
        // the rule (which agreements are involved lives on the event/audit rows).
        'scope_conflict_producer_wide' => 'Cannot activate a Producer-wide agreement for Producer :producer: a per-Club agreement is already active. A Producer\'s Producer-wide and per-Club agreements are mutually exclusive — terminate or supersede the active per-Club agreement first.',
        'scope_conflict_club_scope' => 'Cannot activate a per-Club agreement for Producer :producer: a Producer-wide agreement is already active. A Producer\'s Producer-wide and per-Club agreements are mutually exclusive — terminate or supersede the active Producer-wide agreement first.',
        // Settlement-cadence closed-set boundary validation (change parties-module-k-br-guards, task 3.1; design D4;
        // party-registry — Requirement: ProducerAgreement; BR-K-Agreement-2 / canon MVP-DEC-010). CreateProducerAgreement
        // resolves the free-text cadence operand against the closed SettlementCadence set server-side; an out-of-set/typo
        // token (`annual`, a sub-monthly cadence, a misspelling) is rejected at the boundary — ahead of the raw ValueError
        // the enum cast would throw — persisting no agreement/event. The three accepted tokens are the enum backing values
        // (label "semi-annual" → token `semi_annual`). :cadence echoes the offending operator-supplied token — a cadence
        // token is NOT personal data (the :country / :producer / :club id discipline), so it IS interpolated.
        'invalid_settlement_cadence' => 'Cannot create a ProducerAgreement: ":cadence" is not a valid settlement cadence. The settlement cadence must be one of quarterly, monthly or semi_annual.',
    ],
    'customer' => [
        // § 4.1 / BR-K-Identity-1 rejection (design D5): a Customer's email is globally unique. The reason
        // names the rule and DELIBERATELY omits the email — an email is PII (GDPR) and this message can reach
        // logs (unlike the producer-id references above, which are not PII). The operator supplied the value, so
        // the rule alone is fully actionable.
        'duplicate_email' => 'Cannot create a Customer: a Customer with this email address already exists. Each Customer email must be globally unique.',
        // Registration age gate (change parties-module-k-br-guards, design D7; party-registry — Requirement:
        // Registration Age Gate; BR-K-Identity-6 / canon MVP-DEC-022; BMD § 2.8). CreateCustomer blocks a
        // registration whose self-attested date of birth implies an age below the configurable platform minimum
        // (default 18) — OR that omits a date of birth (attestation is mandatory) — creating no Customer/Account/
        // event. The copy names the rule and interpolates ONLY the :min_age platform constant (a public config
        // value, not PII); the date of birth and the derived age are PII (the duplicate_email / gate_not_met
        // discipline) and are DELIBERATELY never surfaced, so the reason is safe to reach logs.
        'below_minimum_registration_age' => 'Cannot register this Customer: the self-attested date of birth is below the platform minimum registration age of :min_age. Registration requires an age of at least :min_age years.',
        'missing_date_of_birth' => 'Cannot register this Customer: a self-attested date of birth is required to verify the minimum registration age of :min_age. Registration requires a date of birth at or above :min_age years.',
        // Customer status FSM `pending → active → …` (parties-membership-activation, design L6; § 4.1 /
        // AC-K-FSM-1) illegal-transition reason. :state is the offending from-state token (a business enum
        // value, not PII).
        'cannot_activate' => 'Cannot activate this Customer from state :state. A Customer activates only from pending.',
        // Composite onboarding-gate rejection (parties-membership-activation, design L6; § 4.1 / AC-K-J-1 +
        // AC-K-BR-Identity-3). Raised by ActivateCustomer when the conjunctive gate is unmet. The reason names
        // the gate CONDITIONS only — it interpolates NOTHING: the offending acceptance values (verification /
        // T&C / privacy timestamps) are PII and this message can reach logs, so the rule alone is surfaced.
        'gate_not_met' => 'Cannot activate this Customer: the onboarding gate is not met. Activation requires a verified email, accepted terms and privacy, a passed sanctions screening, and cleared KYC where required.',
        // Customer status FSM `active → suspended | closed`, `suspended → active` (parties-membership-
        // suspension, design L4/L7; § 4.1 / AC-K-FSM-1). Suspension and closure are explicit (manual or via
        // the Hold coupling), never auto-driven by a Profile state or a KYC/sanctions verdict
        // (AC-K-BR-Customer-1). :state is the offending from-state token (a business enum value, not PII).
        'cannot_suspend' => 'Cannot suspend this Customer from state :state. A Customer suspends only from active.',
        'cannot_reactivate' => 'Cannot reactivate this Customer from state :state. A Customer reactivates only from suspended.',
        'cannot_close' => 'Cannot close this Customer from state :state. A Customer closes only from active or suspended.',
    ],
    'profile' => [
        // BR-K-Identity-2 rejection (design D8): a Customer holds at most one NON-TERMINAL Profile per Club, so
        // a second live Profile for a (Customer, Club) pair is rejected. :customer / :club are operator-facing
        // id references (not PII), so they are interpolated to make the reason self-documenting (unlike the
        // duplicate_email reason, which omits the PII email).
        'duplicate_for_club' => 'Cannot create a Profile: Customer :customer already has a live Profile in Club :club. A Customer may hold at most one non-terminal Profile per Club.',
        // Profile membership FSM `applied → approved | rejected → active` (parties-membership-activation,
        // design L2/L4; § 4.2.1 / AC-K-FSM-2) illegal-transition reasons. Approve/decline are audit-only writes
        // (no Profile event — L2); activation records ProfileActivated. :state is the offending from-state token
        // (a business enum value, not PII).
        'cannot_approve' => 'Cannot approve this Profile from state :state. A Profile is approved only from applied.',
        'cannot_reject' => 'Cannot decline this Profile from state :state. A Profile is declined only from applied.',
        'cannot_activate' => 'Cannot activate this Profile from state :state. A Profile activates only from approved.',
        // Profile status FSM off `active` (parties-membership-suspension, design L4/L5; § 4.2.1 /
        // AC-K-FSM-2): `active ↔ suspended`, `active → lapsed → active` (30-day grace, DEC-034),
        // `active | lapsed → cancelled` (audit-only — L2), `active → inactive`. cannot_renew also rejects a
        // renewal past the grace window. :state is the offending from-state token (a business enum value,
        // not PII).
        'cannot_suspend' => 'Cannot suspend this Profile from state :state. A Profile suspends only from active.',
        'cannot_reactivate' => 'Cannot reactivate this Profile from state :state. A Profile reactivates only from suspended.',
        'cannot_lapse' => 'Cannot lapse this Profile from state :state. A Profile lapses only from active.',
        'cannot_renew' => 'Cannot renew this Profile from state :state. A Profile renews only from lapsed within the grace window.',
        'cannot_cancel' => 'Cannot cancel this Profile from state :state. A Profile cancels only from active or lapsed.',
        'cannot_deactivate' => 'Cannot deactivate this Profile from state :state. A Profile deactivates only from active.',
        // Hero-Package capacity rejection (change parties-hero-package, design D8; party-registry — Requirement:
        // Hero Package Capacity Invariant; canon MVP-DEC-017 / § 13.1 / AC-K-J-13). Raised by the only two
        // seat-consuming transitions with no edge left to take at parity: an approve of an ALREADY-`waiting_list`
        // Profile whose Club is still full, and a within-grace renewal (`lapsed → active` re-consumes a seat). An
        // `applied` Profile at parity is diverted to `waiting_list` instead, never rejected. :state is the offending
        // from-state token; :occupied and :capacity are Club-level seat cardinals — all three are business values,
        // never PII. The copy names the seat set, so the operator knows which memberships hold the seats it counts.
        'club_at_capacity' => 'Cannot admit this Profile to its Club from state :state. The Club is at its Hero-Package capacity — :occupied of :capacity seats are occupied. Only Active and Suspended memberships hold a seat; one must be released before a further Profile can become active.',
    ],
    'account' => [
        // Account status FSM `active → suspended → closed`, `suspended → active` (parties-membership-
        // suspension, design L4/L8; § 4.7 / AC-K-FSM-9). The Account is born `active` (co-provisioned with
        // its Customer) — there is NO ActivateAccount, only the restore ReactivateAccount; all Account
        // transitions are audit-only (§ 15 names no Account event). :state is the offending from-state token
        // (a business enum value, not PII).
        'cannot_suspend' => 'Cannot suspend this Account from state :state. An Account suspends only from active.',
        'cannot_reactivate' => 'Cannot reactivate this Account from state :state. An Account reactivates only from suspended.',
        'cannot_close' => 'Cannot close this Account from state :state. An Account closes only from active or suspended.',
    ],
    'kyc' => [
        // KYC FSM `not_required → pending → verified | rejected` (parties-compliance, design L2; § 9.1
        // Customer-side / § 4.4 Producer-side — one shared domain at both levels). The require/verify/reject
        // guards back both Customer and Producer KYC; waive is the Producer-only operator deselect to
        // not_required. :state is the offending from-state token (a business enum value, not PII).
        'cannot_require' => 'Cannot require KYC from state :state. KYC moves to pending only from not_required.',
        'cannot_verify' => 'Cannot record KYC verified from state :state. KYC verifies only from pending.',
        'cannot_reject' => 'Cannot record KYC rejected from state :state. KYC rejects only from pending.',
        'cannot_waive' => 'Cannot waive KYC from state :state. The operator deselect applies only to an outstanding KYC requirement.',
    ],
    'sanctions' => [
        // Sanctions screening FSM `pending → passed | failed | under_review`, `under_review → passed | failed`
        // (parties-compliance, design L4; § 9.2). Onboarding must be the first screening; a screening that
        // resolves an open review is valid only from under_review. :state is the offending from-state token
        // (not PII); onboarding_already_screened names only the rule (the prior-screening timestamp is PII).
        'onboarding_already_screened' => 'Cannot record an onboarding sanctions screening: this Customer has already been screened. The onboarding screening is a Customer\'s first screening only.',
        'cannot_resolve' => 'Cannot resolve the sanctions screening from state :state. Only an under_review screening resolves to passed or failed.',
    ],
    'hold' => [
        // Hold lift discipline (parties-holds, design L2; § 4.8.1 DEC-160 / AC-K-FSM-11; ADR
        // 2026-06-18-hold-lift-discipline-per-type). LiftHold (the operator path) throws IllegalHoldLift
        // on a rejected lift. :type is the offending Hold-type token, :state the offending lifecycle-status
        // token — both business enum values, not PII (the same discipline as the kyc/producer :state reasons).
        'cannot_lift_auto_managed' => 'Cannot lift this :type Hold from the operator path. An auto-managed Hold lifts only on its system clearing signal, never by an operator.',
        'cannot_lift_not_active' => 'Cannot lift a Hold from state :state. A Hold lifts only from active.',
    ],
    'club_credit' => [
        // Club Credit FSM `active → redeemed | forfeited`, with the order-cancellation restore edge
        // `redeemed → active` (change club-credit, design L4/L6/L7; party-registry — Requirements: Club
        // Credit Redemption and Carry-Forward, Club Credit Forfeiture and Restoration; § 11). The four
        // within-module writer Actions (Issue/Apply/Forfeit/Restore) throw these on a rejected call.
        // :state is the offending from-state token (a business enum value, not PII).
        'cannot_apply' => 'Cannot apply this Club Credit from state :state. A Club Credit is applied only from active.',
        'cannot_forfeit' => 'Cannot forfeit this Club Credit from state :state. A Club Credit is forfeited only from active.',
        'cannot_restore' => 'Cannot restore this Club Credit from state :state. A Club Credit is restored only from redeemed.',
        // Issuance preconditions (§ 11.1; design L2): issuance is gated on the owning Club's credit policy
        // and fee. :club is the operator-facing club-id reference (not PII); the credit amount is the Club
        // fee verbatim (full-fee → full-credit; K.18 scaling deferred), so a fee-less Club cannot define one.
        'issuance_no_credit_policy' => 'Cannot issue a Club Credit: Club :club does not generate credit. Club Credit issuance requires a Club with a credit policy.',
        'issuance_no_fee' => 'Cannot issue a Club Credit: Club :club has no membership fee. The credit amount is the Club fee, so a Club without a fee cannot issue credit.',
        // Redemption preconditions on an otherwise-active credit (§ 11.2 / § 10.1; design L6).
        // currency_mismatch names the two ISO 4217 codes (:expected = credit currency, :actual = redeemed
        // currency) — there is no FX in Module K. over_application / frozen_while_suspended name the
        // operator-facing :credit id (not PII); the money balance is DELIBERATELY kept out of every message
        // (a balance is customer financial data).
        'currency_mismatch' => 'Cannot apply this Club Credit: the redeemed amount is in :actual but the credit is held in :expected. A Club Credit is redeemed only in its own currency — there is no currency conversion.',
        'over_application' => 'Cannot apply Club Credit :credit: the redeemed amount exceeds its remaining balance. A redemption may not exceed the remaining credit.',
        'frozen_while_suspended' => 'Cannot apply Club Credit :credit: the owning Profile is suspended, which freezes the credit. No redemption is allowed while the Profile is suspended.',
        // Restoration precondition (§ 11; design L1/L7): restoring a redeemed credit would breach the
        // one-active-per-Profile partial index if a replacement was already issued. :credit is the
        // operator-facing id (not PII).
        'restore_active_conflict' => 'Cannot restore Club Credit :credit: the owning Profile already holds another active Club Credit. A Profile may hold at most one active Club Credit at a time.',
    ],
    'anonymisation' => [
        // GDPR right-to-erasure rejection (change parties-anonymisation, design D2; canon MVP-DEC-015 — ADR
        // 2026-07-02-adopt-dec-015-anonymisation-hold-block-set; § 8.2 / AC-K-J-9a). `AnonymiseCustomer`
        // overwrites the Customer PII + its Addresses' personal fields in place; it is ORTHOGONAL to the status
        // FSM (anonymises from any status), IDEMPOTENT (a re-run is a no-op, not a throw) and has NO illegal-state
        // edge — so its ONLY rejection is the Hold-precedence gate. The gate blocks iff an active `compliance`
        // Hold covers the Customer (`compliance`-only over the 8-type set; there is NO `sanctions` Hold type —
        // sanctions is the separate `sanctions_status` FSM); every other Hold type proceeds. :customer is the
        // operator-facing Customer id — an operator reference, NOT PII (a digit, like the profile :customer /
        // club_credit :credit ids) — so it is interpolated to make the reason self-documenting; the copy names
        // the rule and interpolates NO personal data (name / email / phone / date-of-birth), so it is safe to
        // reach logs (the DuplicateCustomerEmail PII-free discipline).
        'blocked_by_compliance_hold' => 'Cannot anonymise Customer :customer: an active compliance Hold requires the Customer\'s identifiable data to be retained. Anonymisation proceeds only once the compliance Hold is lifted.',
    ],
    'address' => [
        // Customer Address country-code boundary validation (change parties-anonymisation, task 2.1; design D4;
        // party-registry — Requirement: Customer Address). `country_code` is a fixed-width ISO 3166-1 alpha-2
        // code (like the ISO 4217 currency codes) validated at the CreateCustomerAddress action boundary — two
        // uppercase letters — NOT a DB enum/CHECK. No launch country-set exists (collectors are international), so
        // the guard validates FORMAT. :country echoes the offending operator-supplied value — a country code is
        // NOT personal data (unlike an email), so it IS interpolated (the :producer / :club id discipline).
        'invalid_country_code' => 'Cannot create the Address: ":country" is not a valid ISO 3166-1 alpha-2 country code. A country code must be two uppercase letters (for example IT, FR or GB).',
    ],
    'compliance_review' => [
        // Human-readable DOMAIN labels for the Compliance review-queue enums (change parties-enhanced-kyc-threshold,
        // design D6; party-registry — Requirements: Enhanced-KYC Threshold Detection, Compliance Review Queue).
        // Unlike the rejection reasons above (surfaced by an Action's exception), these render the
        // ComplianceReviewReason / ThresholdKind BACKING VALUES on a READ surface — the operator console's read-only
        // enhanced-KYC panel (`operator_console.customer.compliance_reviews.*`, task 6.1), a future Compliance
        // dashboard. They live here in the Module-K domain-copy file (not in operator_console.php) because a review's
        // reason / threshold is DOMAIN vocabulary, not console chrome; a surface maps the enum `->value` through these
        // keys (the enums carry NO label() method — the repo convention; SanctionsStatus / HoldType have none). Keyed
        // by the persisted token so a value renders whatever surface reads it. These compliance-review labels are
        // EN-only (lang/it/parties.php covers only the `approval` group); under `it` they fall back per-key to EN (DEC-127).
        'reason' => [
            // ComplianceReviewReason::EnhancedKycThreshold — the SOLE reason in this change (a Customer crossing the
            // €10k single-transaction OR €50k rolling-trailing-12-month cumulative enhanced-KYC threshold, DEC-035).
            'enhanced_kyc_threshold' => 'Enhanced-KYC threshold',
        ],
        'threshold_kind' => [
            // ThresholdKind — which of the two INDEPENDENT (OR) DEC-035 signals tripped. The `cumulative_annual` token
            // measures a ROLLING trailing-12-month total (design D3 — NOT calendar-YTD); the label says "rolling" so it
            // cannot be misread as a calendar-year reset that the token name might suggest.
            'single_transaction' => 'Single transaction',
            'cumulative_annual' => 'Rolling 12-month cumulative',
        ],
    ],
];
