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
    ],
    'club' => [
        // BR-K-Club-1 rejection (design D3/D4): a Club requires exactly one EXISTING operating Producer.
        // :producer is the operator-facing producer-id reference (not PII).
        'missing_producer' => 'Cannot create a Club: no operating Producer exists for reference :producer. A Club requires exactly one existing operating Producer.',
        // Club FSM `active → sunset → closed` (parties-producer-lifecycle, design L2; § 4.3) illegal-transition
        // reasons. :state is the offending from-state token (a business enum value, not PII).
        'cannot_sunset' => 'Cannot sunset this Club from state :state. A Club sunsets only from active.',
        'cannot_close' => 'Cannot close this Club from state :state. A Club closes only from sunset.',
    ],
    'producer_agreement' => [
        // § 4.6 rejection (design D3/D4): a ProducerAgreement references exactly one EXISTING Producer.
        // :producer is the operator-facing producer-id reference (not PII).
        'missing_producer' => 'Cannot create a ProducerAgreement: no Producer exists for reference :producer. A ProducerAgreement requires exactly one existing Producer.',
        // ProducerAgreement FSM `draft → active → superseded | terminated` (parties-producer-lifecycle,
        // design L2; § 4.6.1) illegal-transition reasons. :state is the offending from-state token (not PII).
        'cannot_activate' => 'Cannot activate this ProducerAgreement from state :state. An agreement activates only from draft.',
        'cannot_terminate' => 'Cannot terminate this ProducerAgreement from state :state. An agreement terminates only from active.',
    ],
    'customer' => [
        // § 4.1 / BR-K-Identity-1 rejection (design D5): a Customer's email is globally unique. The reason
        // names the rule and DELIBERATELY omits the email — an email is PII (GDPR) and this message can reach
        // logs (unlike the producer-id references above, which are not PII). The operator supplied the value, so
        // the rule alone is fully actionable.
        'duplicate_email' => 'Cannot create a Customer: a Customer with this email address already exists. Each Customer email must be globally unique.',
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
];
