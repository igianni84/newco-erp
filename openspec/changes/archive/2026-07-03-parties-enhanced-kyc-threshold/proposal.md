## Why

NewCo's **enhanced-KYC AML floor is only a seam**: the `enhanced_kyc_flag` / `enhanced_kyc_at` columns exist (parties-compliance) but nothing sets them, there is **no Compliance review-queue**, and the AML-threshold sanctions re-screen path is unbuilt — the truth spec's *Customer KYC Lifecycle* still reads *"the detection … is deferred; only the fields ship"* and *Customer Sanctions Screening Lifecycle* still reads *"the AML-threshold auto re-screen … [is] deferred"* (Module K Verdict; Remediation_Tracker RM-02, P0 floor, size M). `AC-K-J-7a` and `AC-K-EVT-12a` are both Fail/Gap. **This is that change** (RM-02, the Round-2 P0 compliance-floor build after RM-01 anonymisation). Its substance (DEC-035 + DEC-030) is native to the frozen `spec/`, so — unlike RM-01 — it needs **no adoption ADR** (tracker ADR? = "—").

## What Changes

- **Add the enhanced-KYC threshold-detection workflow** (Module K) — a Customer crossing **€10,000 in a single transaction OR €50,000 rolling trailing-12-month cumulative** (independent OR triggers, DEC-035) is escalated: in one transaction the workflow latches `enhanced_kyc_flag` + `enhanced_kyc_at`, creates a Compliance review-queue entry, records a PII-free `CustomerEnhancedKycReviewRequired` event, and initiates the lightweight AML re-screen. Idempotent — the escalation fires at most once per Customer (latched on the flag). `AC-K-J-7a`.
- **Add the Compliance review-queue** (`parties_compliance_reviews`) — a within-module store of Compliance work-items; each entry records the Customer, the `reason` (`enhanced_kyc_threshold`), the tripping `threshold_kind` (`single_transaction` | `cumulative_annual`) + amount (EUR minor units), and a `resolved_at` (NULL = open; boolean-derivable, no FSM — enhanced-KYC is handled operationally, § 9.1). `AC-K-J-7a`.
- **Add the AML-threshold lightweight re-screen** — on a breach the workflow records an **`under_review`** sanctions verdict with `trigger_source = aml_threshold` through the sole screening-writer (`RecordCustomerScreening`, unchanged), **blocking the Customer until Compliance resolves it**; the resolution records the matching `CustomerRescreening{Passed,Failed}` — the same outcome events as the cadence path. `AC-K-EVT-12a`. (`ScreeningTriggerSource::AmlThreshold` and the four screening events already exist.)
- **Add the two trigger paths sharing one workflow** — (i) a **periodic background job** (a daily scheduled command) built now, running as a scheduler tick (not a queued consumer — substrate ADR); (ii) the **at-order-completion check** invoking the same workflow, its trigger wired by Module S when checkout lands. Both produce identical state.
- **Add the Customer transaction-totals read-contract seam** — `CustomerTransactionTotalsReader` (largest single transaction; rolling trailing-12-month cumulative, EUR). The real source is Module S order/invoice history (unbuilt); the launch binding is a **null adapter returning zero totals**, so detection is a correct no-op until Module S provides it (no cross-module DB access — invariant 10).
- **Operator console:** surface the enhanced-KYC flag + open review-queue entries read-only on the Customer console (visibility only; the resolve action is deferred).
- **Truth-spec deltas** (`party-registry`): **ADD** *Enhanced-KYC Threshold Detection* + *Compliance Review Queue*; **MODIFY** *Customer KYC Lifecycle* (the enhanced-KYC detection now lands) + *Customer Sanctions Screening Lifecycle* (the AML-threshold re-screen now lands; the 12-month cadence job stays deferred).

### Slice boundary — deliberately NOT in this change

| Deferred concern | Future change / owner | Why not here |
|---|---|---|
| **Real Customer transaction-totals source** (order/invoice EUR history) | Module S (Commerce) — Build Workplan Phase 4 | Module S is a stub; RM-02 ships the `CustomerTransactionTotalsReader` contract + a null adapter; Module S provides the real adapter when its checkout/order history lands. |
| **At-order-completion trigger wiring** | Module S (checkout, DEC-113 gate) | RM-02 builds the workflow both paths share and the periodic driver; the order-completion trigger is a Module-S seam (the RM-01 deferred-consumer pattern). |
| **12-month re-screen cadence job** (reads `next_rescreen_at`) | later Parties change | A *separate* deferred seam; RM-02 lands only the **AML-threshold** re-screen path, not the periodic-cadence job. |
| **Screening-vendor adapter** (automated verdicts) | dev-team call (DEC-073), post-launch | Manual-first (§ 9.5, § 7 EDoD): the screen is the floor, the vendor integration is deferrable. |
| **Enhanced-KYC document workflow as a state machine** | future-DEC (§ 17.2 item 14) | § 9.1 / § 7: no enhanced-KYC state machine beyond the flag+timestamp; the review is handled operationally. |
| **Country-change automated sanctions detection** | roadmap (§ 9.2 / § 17.2 item 16) | "NOT enabled at NewCo launch" (signal-to-noise). |
| **Review-queue resolve/close operator action** | later Parties change | RM-02 ships entries creatable + readable (`resolved_at` open); resolution is handled operationally (no FSM). |
| **Compliance ad-hoc re-screen path** | — (already shipped) | `RecordCustomerScreening` + the `ViewCustomer` `compliance_ad_hoc` option already exist; RM-02 covers only the automated AML-threshold path. |
| **Order-completion sanctions/Hold gate** (enforcement of `sanctions = passed`) | Module S (S.15, DEC-181) | Module K is sanctions-blind; the enforcement point is Module S's (Phase 4). |

## Capabilities

### New Capabilities

_(none — the review-queue is a Module K concern; it lands as new requirements inside the existing `party-registry` capability, per the RM-01 precedent.)_

### Modified Capabilities

- `party-registry`: **ADD** *Enhanced-KYC Threshold Detection* (the €10k/€50k detection workflow, both trigger paths, the totals-reader seam) and *Compliance Review Queue* (the `parties_compliance_reviews` entity + the `CustomerEnhancedKycReviewRequired` event). **MODIFY** *Customer KYC Lifecycle* (enhanced-KYC detection lands — flips the "detection is deferred" paragraph + scenario) and *Customer Sanctions Screening Lifecycle* (the AML-threshold re-screen lands — flips the "AML detection deferred" paragraph + scenario; the 12-month cadence job stays deferred).

## Impact

- **New code (Module K):** migration `parties_compliance_reviews`; enums `ComplianceReviewReason`, `ThresholdKind`; model `ComplianceReview`; event `CustomerEnhancedKycReviewRequired`; contract `CustomerTransactionTotalsReader` + DTO `CustomerTransactionTotals` + `NullCustomerTransactionTotalsReader` (bound in `PartiesServiceProvider`); actions `CreateComplianceReview` (Create*) and `EvaluateEnhancedKycThreshold` (non-Create — added to the exact-set whitelist in `ComplianceIndependenceTest`); console command `ScanEnhancedKycThresholds` (registered in `bootstrap/app.php`, scheduled daily in `routes/console.php`).
- **Reused unchanged:** `RecordCustomerScreening` (sole sanctions-writer — the `(under_review, aml_threshold)` call is already supported), `ScreeningTriggerSource::AmlThreshold`, the four `Customer{Onboarding,Re}Screening*` events, `App\Platform\Money\Money`, `ActorContext`, `DomainEventRecorder`.
- **No ADR gate tripped:** DEC-035/DEC-030 are native to frozen `spec/04-decisions/decisions.md`; the periodic job is a **scheduler tick**, explicitly excluded from the queue-driver gate by `decisions/2026-06-12-event-substrate-and-audit-store.md`. No new dependency.
- **i18n:** EN (+ IT for console) copy for the review-reason label and the console surface; no hardcoded strings (invariant 12).
- **Migrations:** Postgres-truthful + SQLite-compatible, additive-only; no PG extension.
