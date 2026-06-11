# NewCo ERP — Module 0 (PIM) Acceptance Criteria — v0.3-MVP

- **Version**: v0.3-MVP (Phase D re-baseline — the launch-MVP acceptance contract for Module 0; re-cut from the PAOLO-VALIDATED v0.1)
- **Date**: 2026-06-07
- **Status**: **RATIFIED by Paolo 2026-06-07** (Phase D re-baseline). The **lightest acceptance delta of any module** (cut-sheet §5): Module 0 scope is **unchanged** (KEEP-in-full), so this doc only (a) applies the **naming cascade** (Wine→Product) to the criteria, (b) re-anchors to the v0.3-MVP PRD, and (c) **adds** a generalisation-guardrail section (§6.4). **No criterion is removed** (nothing was cut).
- **Owner**: Paolo (product sign-off authority)
- **Companion spec**: [`../02-prd/Module_0_PRD_v0.3-MVP.md`](../02-prd/Module_0_PRD_v0.3-MVP.md) — the source of truth this document validates against. The PRD says *what to build*; this document says *what passes*. Together they are the dev-team's complete brief for the launch-MVP Module 0.
- **Predecessor (re-cut from)**: [`../../reference/v1.1/01-prd/Module_0_Acceptance_v0.1.md`](../../reference/v1.1/01-prd/Module_0_Acceptance_v0.1.md) — the **PAOLO-VALIDATED** (2026-05-15) v1.1 acceptance template (88 criteria; format locked). `greenfield/` is frozen (plan R4); this is a derivative under `mvp/`.
- **Audience** (three concurrent uses): **Paolo** at module-delivery sign-off (verdict report + spot-checks); **dev team** during build (the definition of done, read alongside the PRD from day one); **AI coding agents** during code generation (AUTO criteria as fitness functions in the build loop).
- **Purpose**: the demonstrable behaviours that, taken together, constitute "Module 0 is delivered as specified per v0.3-MVP." Each criterion is traceable to a PRD anchor (BR-NNN / event / FSM transition / DEC / §) and tagged AUTO / MIXED / HUMAN.
- **Methodology DECs binding this document**: DEC-073 (product-spec layer; criteria are business-behaviour, not tech-implementation), DEC-074 (self-contained; anchors restated inline), `feedback_prd_rr_approval` (approval role-count is admin-configurable — out of scope as a build-time concern, tested only as separation-of-duties). **MVP additions:** the generalisation brief §6 definition-of-done checklist is verified in §6.4; the **non-behavioural-for-wine guarantee** is the safety property that lets the hardened v1.1 criteria be re-used unchanged (renamed) for the `WINE` type.
- **What this document is NOT**: engineering Definition of Done (coverage thresholds, performance budgets, retry mechanics, schema design); UI/UX acceptance (layouts, copy, accessibility, responsive design); operational R&R / approval-tier *policy* (admin-configurable); non-functional concerns not anchored to a BR/DEC at PRD level.

---

## §0 What changed from v0.1 (the re-cut delta)

Module 0 is **KEEP-in-full + GENERALISE** with **~0 net-new deferrals**, so this acceptance re-cut is mechanical and additive:

1. **Naming cascade applied to every criterion.** `Wine Master → Product Master`, `Wine Variant → Product Variant`, `Bottle Reference (BR) → Product Reference (PR)`, and the `WineMaster*/WineVariant*/BottleReference*` events → `ProductMaster*/ProductVariant*/ProductReference*`. **Wine-display aliases** ("Wine Master," "Bottle Reference," "bottle," "vintage") are retained where they aid wine-facing readers. **Behaviour is identical** — every renamed criterion tests the same business behaviour as its v0.1 original.
2. **Re-anchored to the v0.3-MVP PRD.** PRD §-numbers now refer to [`../02-prd/Module_0_PRD_v0.3-MVP.md`](../02-prd/Module_0_PRD_v0.3-MVP.md). The entity-spine subsections shifted by +1 (new §3.1 Product Type): Product Master §3.2, Product Variant §3.3, Product Reference §3.4, Format §3.5, Case Configuration §3.6, Intrinsic SKU §3.7, Composite SKU §3.8, attribute model §3.9. The deferred set moved to §17 (was §16); the generalisation guardrails are PRD §16; the naming-cascade source-of-truth is PRD §18.
3. **Two existing buckets generalised in substance (non-behavioural for wine):** **BR-Identity-1** is expressed as a **type-defined identity key** (`WINE` = producer + product name + appellation — unchanged value); the **enrichment** criteria (§2 J-1/J-3/J-12, §4.8 Resilience, §5 EVT-8) test a **pluggable adapter** (`WINE` = LWIN) with the **manual baseline as the launch-critical path** (Q3 manual-first — see §0.1).
4. **New section §6.4 — Generalisation guardrails & the non-behavioural guarantee** (12 criteria, AC-0-GEN-1..12), verifying the brief §6 definition-of-done checklist + the three ratified Qs (Q1 Composite SKU KEPT; Q2 lighter-approval config; Q3 manual-first enrichment).
5. **Nothing removed.** The deferred set is v1.1's already-deferred items, carried verbatim to the roadmap (§7); no launch criterion is cut.

### §0.1 The Q3 manual-first enrichment posture (affects how LWIN criteria are scheduled)

Per cut-sheet Q3 (ratified 2026-06-07), the **manual baseline path is the launch-critical enrichment path**; the `WINE` LWIN (Liv-ex) adapter drops into the pluggable interface **when the vendor lands — at launch if ready, otherwise post-launch, with no rework.** Therefore:
- The **manual-baseline criteria** (creation, dedup, full chain, approval, events) are **launch-blocking** and verified at Module 0 handover.
- The **LWIN-adapter-specific criteria** (auto-populate from Liv-ex, producer-name match, capture-then-own snapshot, retry/resilience) carry an inline **"verified when the WINE/LWIN adapter lands"** note — they are not removed (the adapter behaviour is unchanged when it lands), but they are **not launch-blocking** if Liv-ex onboarding slips past launch. This keeps catalog enrichment **off the launch critical path** while preserving the full adapter acceptance for when it lands.

---

## §1 How to use this document

### §1.1 Verification tags

- **AUTO** — an AI agent or automated harness reads the criterion + spec anchor + running system (event stream, entity state, API responses, audit trail) and produces a PASS/FAIL verdict with evidence. Paolo reviews the verdict batch.
- **MIXED** — AI prepares the evidence; Paolo confirms a judgment call (rendering quality, prose readability, the non-behavioural-for-wine parity proof).
- **HUMAN** — Paolo executes personally (a single end-to-end demo session + subjective spot-checks).

**Distribution for Module 0 v0.3-MVP: ~100 total criteria** — the v0.1 88 (86 AUTO / 1 MIXED / 1 HUMAN) **+ 12 generalisation criteria (11 AUTO / 1 MIXED)** → **97 AUTO / 2 MIXED / 1 HUMAN.** Paolo's hands-on load: **2 MIXED items + 1 end-to-end demo session.**

### §1.2 Build-time usage

Consulted from day one, not only at handover. The dev reads the PRD + this doc together; AUTO criteria wire into CI as scaffolding lands (the AUTO PASS rate is a continuous completion signal); AI coding agents treat AUTO criteria as fitness functions (read PRD anchor → generate code → run AUTO → iterate); MIXED/HUMAN items are scheduled, not surprised; the acceptance doc evolves with the spec in lock-step.

### §1.3 Sign-off cadence

Each criterion lands in **OPEN** (not yet demonstrated) → **DEMOED** (evidence produced) → **ACCEPTED** (Paolo signed off). Module 0 is **delivered** when every §2–§6 criterion is ACCEPTED. Sign-off log at §8.

### §1.4 Anchors

PRD §-numbers refer to [`../02-prd/Module_0_PRD_v0.3-MVP.md`](../02-prd/Module_0_PRD_v0.3-MVP.md). BR-NNN refers to its §13. Event names refer to its §14. FSM states refer to its §4.1. DEC refers to the v1.1 Decision Register (cited inline).

### §1.5 Format conventions (locked at the v0.1 review; carried + extended)

1. **§4 BR statements are verbatim from PRD §13** (self-containment per DEC-074; trivial drift detection). *For v0.3-MVP the verbatim statements carry the naming cascade + the BR-Identity-1 type-defined-key generalisation, matching the v0.3-MVP PRD §13 prose.*
2. **§4 BR→AC pointer rows preserve traceability** (every BR has an explicit AC ID row, even when covered by an upstream §2/§3 criterion).
3. **§6 cross-module criteria verify the PIM-side surface only** (downstream behaviour verified in the receiving module's acceptance doc; no dual-side overlap).
4. **AUTO criteria dependent on consumer modules carry inline "verified when X lands" notes** (PIM emits 22 events; PIM-side schema/emission verified at PIM handover, downstream consumption when the consumer module is built).
5. **(NEW, v0.3-MVP)** **Generalisation criteria live in §6.4** (AC-0-GEN-*); the **non-behavioural-for-wine guarantee** (AC-0-GEN-9) is evidenced by the renamed v0.1 wine-behaviour criteria all passing identically, plus a MIXED parity confirmation against the brief §6 checklist. **LWIN-adapter criteria carry the "verified when the WINE/LWIN adapter lands" note (§0.1).**

---

## §2 Canonical journeys — end-to-end operator flows

Six buckets exercised end-to-end: catalog creation (the `WINE` enrichment adapter [LWIN] / manual baseline / bulk), full activation chain (Product Master → Product Variant → Product Reference → Sellable SKU), Composite SKU, rejection/re-submission, retirement (cascade + blocked-by-references + Layer-1 whitelist reduction), re-activation. Plus one HUMAN demo (AC-0-J-11).

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-0-J-1** | Catalog Operator creates a new Product Master via the `WINE` enrichment adapter by entering an LWIN-7 code; system queries Liv-ex, auto-populates identity fields (name, appellation, region into the `WINE` attribute set, producer candidate), matches to an existing `active` KYC-verified Producer in Module K, saves entity as `draft`. | §5.1; BR-Audit-3 | AUTO — verify auto-populated fields against Liv-ex API response; verify Producer link resolved; verify `draft` persisted. **Verified when the WINE/LWIN adapter lands (§0.1); the manual-baseline equivalent (AC-0-J-3) is the launch-critical path.** |
| **AC-0-J-2** | When the LWIN producer-name candidate has no exact or fuzzy match in Module K, system blocks Product Master activation until a `producer_id` is bound. Product Master can be saved as `draft` in the interim. | §5.1 + §5.4; BR-Producer-1 | AUTO — negative-path: create with unknown producer, attempt activate, assert rejection; register producer, retry, assert success. **(Producer-gate half is launch-critical regardless of adapter; LWIN-match half verified when the adapter lands.)** |
| **AC-0-J-3** | **(Launch-critical baseline.)** When no enrichment adapter is available (the launch default) or Liv-ex is unavailable, the Operator completes creation via the **manual baseline path**. Deduplication (the type-defined identity key, BR-Identity-1) applies identically to the manual and adapter paths. | §5.2 + §5.3; BR-Resilience-1; BR-Identity-1 | AUTO — drive the manual baseline path end-to-end; attempt a duplicate (same producer + product name + appellation), assert rejection. **This is the launch-blocking enrichment criterion.** |
| **AC-0-J-4** | Operator drives a full chain Product Master → Product Variant → Product Reference → Sellable SKU (Intrinsic) → `active`, each through the Creator → Reviewer → Approver workflow with distinct actors. Each `*Activated` event appears in the audit trail in parent-before-child order. | §4.4 + §14.3; BR-Lifecycle-1, BR-Lifecycle-3, BR-SKU-1 | AUTO — run scripted chain with distinct test actors; assert state transitions + event emission order. |
| **AC-0-J-5** | Operator creates a Composite SKU with N ≥ 2 constituent PRs; activation requires every constituent PR to be `active`; once referenced by an `active` Module S Offer, constituent composition becomes immutable. | §3.8; BR-SKU-2, BR-SKU-3, BR-SKU-4 | AUTO — activate constituents, activate cSKU, simulate `active` Offer reference, attempt edit, assert rejection. |
| **AC-0-J-6** | Operator bulk-uploads a CSV/Excel with mixed valid + invalid rows; valid rows enter as `draft`, invalid rows skipped with a detailed error log (row id, field, reason); each valid row individually flows through approval — no batch shortcut. | §6.1–§6.5; BR-BulkImport-1..4 | AUTO — submit fixture batch; assert summary counts, error-log shape, post-batch state, absence of batch-approval API. |
| **AC-0-J-7** | Reviewer or Approver rejects an entity; it stays in `reviewed` with rejection flag + notes; Creator edits in place (no revert-to-draft) and re-submits; approval restarts from review; full rejection history preserved in audit trail. | §4.3; BR-Lifecycle-6 | AUTO — drive a 2-rejection-round scenario; assert state stays `reviewed`, audit trail contains every round. |
| **AC-0-J-8** | Operator-driven cascade retirement of a Product Master with its active children emits retirement events in parent-before-child order (Master → Variant → Reference → SKU). Existing active downstream commercial state remains valid; new commercial commitment against retired entities is blocked. | §4.5 + §4.7 + §14.3; BR-Lifecycle-4 | AUTO — set up Master with 2 Variants × 2 PRs; trigger cascade retire; assert event order; assert new-allocation attempt against retired PR is rejected; in-flight-order-continues check verified when Modules A + C land. |
| **AC-0-J-9** | A PIM entity cannot transition to `retired` while it has active downstream references that have not completed. System surfaces the specific open references blocking retirement. | §4.6; BR-Lifecycle-5 | AUTO — attempt retire PR with one open voucher; assert rejection with open-reference list; close voucher; retry; assert success (voucher creation verified when Modules S + C land; PIM-side rejection logic testable earlier against a stub-able open-reference record). |
| **AC-0-J-10** | A `retired` PIM entity can transition back to `active` via the same approval workflow. For a Product Master, the Producer activation gate is re-checked at re-activation; if the Producer is no longer `active` / KYC-verified, re-activation is blocked. | §4.1 last paragraph + §5.4; BR-Lifecycle-2, BR-Producer-1 | AUTO — drive `retired → reviewed → active` with Producer `active`; then with revoked KYC; assert success + block respectively. |
| **AC-0-J-11** | End-to-end demo session: Paolo observes the Catalog team walking Product Master creation (manual baseline + LWIN if the adapter has landed), Product Variant creation, full chain to Sellable SKU, Composite SKU creation, bulk import, rejection round, cascade retirement, Layer-1 whitelist reduction on an active Variant, re-activation. | §1–§9 (full surface) | HUMAN — single session, ~60–90 min, with dev + catalog team; Paolo signs off on observed behaviour. |
| **AC-0-J-12** | Catalog Operator creates a new Product Variant under an existing `active` Product Master via the `WINE` adapter (LWIN-11 / LWIN-16); system auto-populates vintage-specific fields (vintage year, tasting notes, vintage-level critic scores into the `WINE` attribute set), saves as `draft`, flows through approval to `active`. `ProductVariantCreated` / `ProductVariantActivated` emit at the corresponding transitions. | §5.1 + §3.3 + §14.1; BR-Audit-3 | AUTO — drive Variant creation via LWIN-11; assert vintage-specific fields auto-populated; assert `draft`; drive through approval; assert `active` + `ProductVariantActivated`. **Auto-populate half verified when the WINE/LWIN adapter lands (§0.1); the manual-baseline Variant creation + lifecycle + events are launch-critical.** |
| **AC-0-J-13** | Operator reduces an `active` Product Variant's possible-case-configurations whitelist (removes a previously-admissible Case Configuration). Existing Sellable SKUs and Allocations referencing the now-excluded Case Configuration remain valid for their current lifecycle; only new Sellable SKU versions and new Allocations against the excluded entry are blocked. Layer-1 changes do not retroactively invalidate Layer-2 declarations on already-active allocations. | §7.1 | AUTO — set up `active` Variant with whitelist [OWC6, CARTON12, loose]; remove OWC6; assert existing SKU referencing OWC6 remains valid, new SKU referencing OWC6 blocked at activation, existing Layer-2 declaration on `active` Allocation unchanged (downstream SKU + Allocation existence verified when Module A + Module S land; PIM-side whitelist-update + new-SKU-block testable at PIM scope). |

---

## §3 State machine round-trips — entity FSMs

Every PIM entity follows the same 4-state lifecycle (`draft → reviewed → active → retired`) and the same approval workflow at each commercial-impact transition. The seven entities are exercised individually, plus the cascade and gate rules.

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-0-FSM-1** | Product Master traverses `draft → reviewed → active → retired` with `ProductMasterCreated` / `ProductMasterActivated` / `ProductMasterRetired` emitted at the corresponding transitions. | §4.1 + §14.1 + §14.2 | AUTO |
| **AC-0-FSM-2** | Product Variant traverses the same 4-state lifecycle with `ProductVariantCreated` / `ProductVariantActivated` / `ProductVariantRetired`. | §4.1 + §14.1 | AUTO |
| **AC-0-FSM-3** | Product Reference traverses the same 4-state lifecycle with `ProductReferenceCreated` / `ProductReferenceActivated` / `ProductReferenceRetired`. | §4.1 + §14.1 | AUTO |
| **AC-0-FSM-4** | Sellable SKU (Intrinsic) traverses the same 4-state lifecycle with `SellableSKUCreated` / `SellableSKUActivated` / `SellableSKURetired`. | §4.1 + §14.1 | AUTO |
| **AC-0-FSM-5** | Composite SKU traverses the same 4-state lifecycle with `CompositeSKUCreated` / `CompositeSKUActivated` / `CompositeSKURetired` — emitted as a **distinct event family** from Sellable SKU (Intrinsic). | §4.1 + §14.1 + §14.2 | AUTO |
| **AC-0-FSM-6** | Format traverses the same 4-state lifecycle with `FormatCreated` / `FormatActivated` / `FormatRetired`. | §4.1 + §14.1 | AUTO |
| **AC-0-FSM-7** | Case Configuration traverses the same 4-state lifecycle with `CaseConfigurationCreated` / `CaseConfigurationActivated` / `CaseConfigurationRetired`. | §4.1 + §14.1 | AUTO |
| **AC-0-FSM-8** | `draft → reviewed` is captured in the audit trail but does NOT emit a distinct cross-module event (review is internal-to-PIM); the next `*Activated` event is the cross-module signal. | §4.2 last paragraph + §14.2 | AUTO — assert event stream for `*Reviewed` is empty across all entity types; assert audit trail contains review records. |
| **AC-0-FSM-9** | Re-activation traverses `retired → reviewed → active` via the standard approval workflow (distinct-actor rule at the configured role-count). | §4.1 last paragraph | AUTO |
| **AC-0-FSM-10** | Activation cascade is enforced at workflow level for every parent-child pair: Variant → Master, PR → Variant, PR → Format, iSKU → PR, iSKU → CC, cSKU → each constituent PR. Child activation is rejected when parent is not `active`. | §4.4; BR-Lifecycle-3 | AUTO — parametrised negative-path test across all 6 pairs. |
| **AC-0-FSM-11** | Retirement cascade: when a parent is retired, existing active children remain valid for their current lifecycle; no new children can be activated under a retired parent. | §4.5; BR-Lifecycle-4 | AUTO |
| **AC-0-FSM-12** | Producer activation gate: a Product Master cannot transition to `active` unless the linked Producer (Module K) is `active` AND KYC-verified at the moment of transition. | §5.4; BR-Producer-1 | AUTO — three negative paths: Producer `draft`, Producer `reviewed`, Producer `active` but not KYC-verified. |
| **AC-0-FSM-13** | KYC-revocation symmetry: if Producer KYC is revoked after Product Masters have activated under it, those Masters remain `active`; only new Master activations (and new child activations under them) are blocked. | §5.4; BR-Producer-2 | AUTO |
| **AC-0-FSM-14** | All bulk-imported entities enter as `draft` regardless of source-file completeness; each individually flows through approval. No batch-approval shortcut. | §6.5; BR-BulkImport-3 | AUTO — verify post-import state uniformly `draft`; verify no batch-approval functionality is exposed. |

---

## §4 Business rule enforcement (28 BRs)

One criterion per business rule in PRD §13. Each row restates the BR **verbatim** from the v0.3-MVP PRD §13 (DEC-074; carries the naming cascade + the BR-Identity-1 type-defined-key generalisation). Pointer rows preserve full BR→AC traceability.

### §4.1 Product identity (BR-Identity-1..4)

| AC ID | BR statement (verbatim from PRD §13.1) | Verification |
|---|---|---|
| **AC-0-BR-Identity-1** | Uniqueness is enforced **per Product Type on a type-defined identity key.** For `WINE` the key is **producer + product name + appellation**: no two `active` `WINE` Product Masters may share that combination. The deduplication check runs on both the enrichment-adapter creation path and the manual baseline path. | AUTO — positive path: create two with different identity, both succeed; negative path: attempt duplicate, assert rejection with clear reason; run on both the adapter and manual paths. **(Manual-path half is launch-critical; adapter-path half verified when the WINE/LWIN adapter lands.)** |
| **AC-0-BR-Identity-2** | Each Product Variant belongs to exactly one Product Master. Each Product Reference references exactly one Product Variant and one Format. No PIM entity belongs to multiple parents. | AUTO — schema-level inspection confirms single-parent references; attempt multi-parent create, assert rejection. |
| **AC-0-BR-Identity-3** | A PR = Product Variant + Format. Case Configuration is **never** part of PR identity. One Sassicaia 2018 in 0.75L is the same PR whether sold loose, in an OWC, or in a carton. Allocations are keyed at PR level. Enforced system-wide. | AUTO — verify PR entity schema has no `case_configuration_id`; verify allocations key at PR level; verify three Sellable SKUs (loose / OWC6 / CARTON12) all reference the same PR. |
| **AC-0-BR-Identity-4** | Once a PR is referenced by an Allocation, voucher, stock position, or commercial Offer, its Product Variant + Format composition cannot be changed. | AUTO — set up referenced PR, attempt to edit Variant or Format binding, assert rejection across all four reference types. |

### §4.2 Lifecycle and governance (BR-Lifecycle-1..6)

| AC ID | BR statement (verbatim from PRD §13.2) | Verification |
|---|---|---|
| **AC-0-BR-Lifecycle-1** | Every PIM entity follows the Creator → Reviewer → Approver workflow; the roles that run must be distinct people; self-approval is never allowed. *(Role-count admin-configurable per Q2 — §4.2; floor holds at any configured depth.)* | AUTO — attempt Creator-Reviewer same-actor and Creator-Approver same-actor, assert rejection; three distinct actors, assert success; **also exercised at the configured 2-step depth — see AC-0-GEN-12.** |
| **AC-0-BR-Lifecycle-2** | Every PIM entity follows `draft → reviewed → active → retired`. Re-activation from `retired` to `active` follows the same approval workflow. | AUTO — already covered by AC-0-FSM-1..7 + AC-0-FSM-9. |
| **AC-0-BR-Lifecycle-3** | A child cannot transition to `active` while its parent is not `active`. Product Master activation also requires its linked Producer (Module K) to be `active` and KYC-verified. Sellable SKU activation requires both the PR and the Case Configuration to be `active`. | AUTO — already covered by AC-0-FSM-10 + AC-0-FSM-12. |
| **AC-0-BR-Lifecycle-4** | When a parent is retired, existing active children remain valid for current references but cannot be used in new commercial commitment. No new children can be activated under a retired parent. | AUTO — already covered by AC-0-FSM-11. |
| **AC-0-BR-Lifecycle-5** | A PIM entity cannot itself be retired while it has active downstream references that have not yet completed. The system surfaces the open references; retirement proceeds after they close. | AUTO — already covered by AC-0-J-9. |
| **AC-0-BR-Lifecycle-6** | A rejected entity stays in `reviewed` with a rejection flag and notes. The Creator edits in place — no revert to `draft` — and re-submits. The approval flow restarts from review. Full rejection history is preserved in the audit trail. | AUTO — already covered by AC-0-J-7. |

### §4.3 Version, audit, data ownership (BR-Audit-1..3)

| AC ID | BR statement (verbatim from PRD §13.3) | Verification |
|---|---|---|
| **AC-0-BR-Audit-1** | Changes to identity-bearing entities create new versions. Old versions are deprecated, never deleted. Full before-and-after state is recorded for every change. | AUTO — drive identity edit on Product Master, assert old version retrievable + new version active + audit row with before+after; drive Composite SKU constituent-composition change, assert same versioning semantics. |
| **AC-0-BR-Audit-2** | Critic scores, tasting notes, and market data are informational metadata. They are **never** used for commercial pricing decisions or allocation logic. Hard constraint. | AUTO — inspect Module S pricing logic + Module A allocation logic API surface; assert neither reads PIM enrichment fields (verified when Module S + Module A land). |
| **AC-0-BR-Audit-3** | Identity data captured from an enrichment adapter at creation (for `WINE`, from Liv-ex) is owned by PIM thereafter. PIM does not auto-sync with future adapter-source changes; the captured snapshot is authoritative. | AUTO — create via the WINE adapter, simulate Liv-ex updating the same record post-creation, assert PIM snapshot unchanged + no auto-sync event. **Verified when the WINE/LWIN adapter lands (§0.1); the capture-then-own principle is adapter-path behaviour.** |

### §4.4 Producer dependency (BR-Producer-1..2)

| AC ID | BR statement (verbatim from PRD §13.4) | Verification |
|---|---|---|
| **AC-0-BR-Producer-1** | A Product Master cannot transition to `active` unless the linked Producer (Module K Producer Registry) is itself `active` and KYC-verified. | AUTO — already covered by AC-0-FSM-12. |
| **AC-0-BR-Producer-2** | If a Producer's KYC verification is revoked after Product Masters have been activated under it, those existing `active` Masters remain `active`; the revocation only blocks *new* Master activations (and new child-entity activations under those Masters) for that Producer. | AUTO — already covered by AC-0-FSM-13. |

### §4.5 Sellable SKU rules (BR-SKU-1..5)

| AC ID | BR statement (verbatim from PRD §13.5) | Verification |
|---|---|---|
| **AC-0-BR-SKU-1** | A Sellable SKU (Intrinsic) = one Product Reference + one Case Configuration + commercial attributes. The activation prerequisite is that the PR and the Case Configuration are both `active`. | AUTO — covered by AC-0-FSM-10 (iSKU → PR, iSKU → CC pairs). |
| **AC-0-BR-SKU-2** | Composite SKUs are originated by Module S; PIM registers them and governs their lifecycle. Module S defines the constituent composition; PIM enforces hierarchy integrity (every constituent PR exists and is `active` at activation time) and lifecycle compliance. | AUTO — drive Composite SKU creation; assert PIM does not validate Module S commercial logic; assert PIM enforces the constituent-active gate. |
| **AC-0-BR-SKU-3** | A Composite SKU is commercially atomic. At sale, one voucher per constituent unit-equivalent; reservations, holds, and fulfilment must succeed for *every* constituent; partial-bundle issuance is not allowed. | AUTO — drive a Composite-SKU sale; assert one voucher per constituent; force one constituent's allocation to fail, assert whole sale rolls back (verified when Modules A + B + C land). |
| **AC-0-BR-SKU-4** | A Composite SKU's constituent composition becomes immutable once referenced by an `active` Offer; the path is retire + register new. | AUTO — covered by AC-0-J-5. |
| **AC-0-BR-SKU-5** | A Composite SKU may carry constituent PRs from one or many producers. PIM does not validate producer composition. Admissibility on a given surface is a Module S Offer-publication validation: club Offers reject mixed-producer sets (DEC-019); Discovery Offers admit them (DEC-061 — *multi-producer Discovery deferred at launch, D7*). PIM is silent. | AUTO — create a multi-producer Composite SKU in PIM, assert acceptance (the N-constituent multi-producer seam is retained even though launch composites are single-producer); Module S surface validation verified in Module S acceptance. **See AC-0-GEN-11 (Q1).** |

### §4.6 Format and Case Configuration (BR-RefData-1..2)

| AC ID | BR statement (verbatim from PRD §13.6) | Verification |
|---|---|---|
| **AC-0-BR-RefData-1** | New Formats can be proposed by any Catalog Operator and require the standard approval before becoming available for new PR creation; a PR cannot activate if its referenced Format is not `active`. *(Admissible Format vocabulary is type-driven — `WINE` = bottle sizes.)* | AUTO — drive Format approval workflow; activation gate covered by AC-0-FSM-10 (PR → Format pair). |
| **AC-0-BR-RefData-2** | Case Configuration carries packaging-form attributes only — units per case, packaging type, physical attributes. It **carries no breakability flag**. Breakability is decided by the §7 layered rule, to which PIM contributes via Layer 1 only (the Product Variant's possible-case-configurations whitelist). | AUTO — inspect Case Configuration schema, assert no `is_breakable`/`breakable`/equivalent field; verify the Layer-1 Product Variant whitelist is the only PIM breakability surface; effective_unbreakable resolution via Module A Layer 2 OR Module S Layer 3 verified in those modules' acceptance docs. |

### §4.7 Bulk import (BR-BulkImport-1..4)

| AC ID | BR statement (verbatim from PRD §13.7) | Verification |
|---|---|---|
| **AC-0-BR-BulkImport-1** | Bulk-import depth is configurable per operation: Product Master only; + Product Variant; + Product Reference; or full chain through Sellable SKU. | AUTO — drive each of the four depth modes; assert correct entity-type set produced. |
| **AC-0-BR-BulkImport-2** | Records that fail validation are skipped and reported in a detailed error log with specific reasons. Valid records proceed regardless. | AUTO — covered by AC-0-J-6. |
| **AC-0-BR-BulkImport-3** | All bulk-imported entities enter as `draft` and follow the standard approval workflow. There is no batch-approval shortcut. | AUTO — covered by AC-0-FSM-14. |
| **AC-0-BR-BulkImport-4** | Re-attempt of failed rows is operator-initiated. PIM does not subscribe to upstream `*Activated` events to auto-replay queued failures; if the cause was an upstream lifecycle gate, the Operator re-submits in a subsequent batch once the upstream entity reaches `active`. | AUTO — inspect PIM event-consumption registry; assert no auto-replay subscription; verify upstream `ProducerActivated` does not trigger auto-replay. |

### §4.8 System resilience (BR-Resilience-1)

| AC ID | BR statement (verbatim from PRD §13.8) | Verification |
|---|---|---|
| **AC-0-BR-Resilience-1** | If a Product Type's enrichment adapter is unavailable during a creation (for `WINE`, the Liv-ex API), PIM retries automatically; if retries fail, the Operator is notified and offered the manual baseline path, which preserves all validation and deduplication. | AUTO — covered by AC-0-J-3 (manual-baseline half, launch-critical); additionally assert the retry policy fires before fallback is offered. **Retry-policy half verified when the WINE/LWIN adapter lands (§0.1).** |

### §4.9 Cross-module contract (BR-Contract-1)

| AC ID | BR statement (verbatim from PRD §13.9) | Verification |
|---|---|---|
| **AC-0-BR-Contract-1** | All PIM lifecycle transitions emit versioned domain events consumed by downstream modules (enumerated in §14). Events are versioned so consumers can evolve independently; PIM guarantees backward compatibility within a major event-schema version. | AUTO — inspect all 22 PIM events for a version field; drive a minor-version additive change, assert downstream consumers (S/A/B/C/D) still consume (full downstream consumption verified when those modules land). |

---

## §5 Domain event emission and consumption

PIM emits 22 events (7 entities × 3 lifecycle events + `EnrichmentDataUpdated`) and consumes 2 from Module K (`ProducerActivated`, `ProducerRetired`). **The generalisation renames the `WineMaster*/WineVariant*/BottleReference*` families to `ProductMaster*/ProductVariant*/ProductReference*` — payload semantics unchanged.** `EnrichmentDataUpdated` is the only PIM event that fires on a post-`active` mutation.

### §5.1 Lifecycle event emission

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-0-EVT-1** | `ProductMasterCreated` fires on creation (`<null> → draft`); `ProductMasterActivated` on `reviewed → active`; `ProductMasterRetired` on `active → retired`. | §14.1 + §14.2 | AUTO |
| **AC-0-EVT-2** | `ProductVariantCreated` / `ProductVariantActivated` / `ProductVariantRetired` fire on their respective transitions. | §14.1 | AUTO |
| **AC-0-EVT-3** | `ProductReferenceCreated` / `ProductReferenceActivated` / `ProductReferenceRetired` fire on their respective transitions. | §14.1 | AUTO |
| **AC-0-EVT-4** | `SellableSKUCreated` / `SellableSKUActivated` / `SellableSKURetired` fire on their respective transitions. | §14.1 | AUTO |
| **AC-0-EVT-5** | `CompositeSKUCreated` / `CompositeSKUActivated` / `CompositeSKURetired` fire on their respective transitions AND are emitted as a **distinct event family** from Sellable SKU (Intrinsic) — downstream consumers dispatch on the family, not a payload discriminator. | §14.1 + §14.2 + §3.8 | AUTO — (a) inspect event registry, assert distinct family (PIM-side, testable at handover); (b) verify downstream consumer wiring dispatches on the family (verified when Module S lands). |
| **AC-0-EVT-6** | `FormatCreated` / `FormatActivated` / `FormatRetired` fire on their respective transitions. | §14.1 | AUTO |
| **AC-0-EVT-7** | `CaseConfigurationCreated` / `CaseConfigurationActivated` / `CaseConfigurationRetired` fire on their respective transitions. | §14.1 | AUTO |
| **AC-0-EVT-8** | `EnrichmentDataUpdated` fires when observational enrichment metadata (critic scores, tasting notes, market data) changes on a Product Variant. Distinct from the lifecycle triplet; enrichment is mutable post-activation. **(Name unchanged by the generalisation.)** | §14.1 last paragraph | AUTO |

### §5.2 Emission semantics

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-0-EVT-9** | `*Created` cover `<null> → draft`; `*Activated` cover `reviewed → active`; `*Retired` cover `active → retired`. The `draft → reviewed` transition does NOT emit a distinct cross-module event. | §14.2 | AUTO — verify no `*Reviewed` events across all 7 entity types. |
| **AC-0-EVT-10** | Activation events emitted in **parent-before-child** order, naturally enforced by the §4.4 activation cascade. | §14.3 | AUTO — drive full activation chain Master → Variant → PR → iSKU; assert arrival order. |
| **AC-0-EVT-11** | Retirement events emitted by operator-driven cascade retirement (§4.7) follow **parent-before-child** order. For single-entity retirements (§4.6), no ordering constraint applies. | §14.3 | AUTO — drive cascade retirement; assert ordering; drive single retire, assert no synthetic ordering imposed. |
| **AC-0-EVT-12** | Downstream consumers tolerate eventual-consistency arrival order; PIM guarantees emission order, not arrival order. Consumers dedupe and reconcile on the parent's current state at consume time. | §14.3 | AUTO — inject out-of-order arrival at one consumer (Module S), assert correct reconciliation (verified when Module S lands). |
| **AC-0-EVT-13** | Events are versioned at schema level; PIM guarantees backward compatibility within a major schema version. | §14.4; BR-Contract-1 | AUTO — covered by AC-0-BR-Contract-1. |

### §5.3 Consumer bindings (renamed)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-0-EVT-14** | Module S consumes `ProductMasterActivated`, `ProductVariantActivated`, `ProductReferenceActivated`, `SellableSKUActivated`, `CompositeSKUActivated` — enabling Offer creation + Price-Book entries; and the corresponding `*Retired` events to flag Offers + Price-Book entries for review. | §14.5 | AUTO — emit each event, assert Module S consumes + correct state change (verified when Module S lands). |
| **AC-0-EVT-15** | Module S consumes `EnrichmentDataUpdated` for marketing surfaces. | §14.5 | AUTO — verified when Module S lands. |
| **AC-0-EVT-16** | Module A consumes `ProductReferenceActivated` to enable Allocation creation; consumes `ProductReferenceRetired` to trigger Allocation review. | §14.5 | AUTO — verified when Module A lands. |
| **AC-0-EVT-17** | Module B consumes `ProductReferenceActivated` for stock-position tracking; consumes `ProductReferenceRetired` + `ProductVariantRetired` + `ProductMasterRetired` to flag inventory for review. | §14.5 | AUTO — verified when Module B lands. |
| **AC-0-EVT-18** | Module C consumes `ProductReferenceRetired` to trigger fulfilment holds where open vouchers exist. | §14.5 | AUTO — verified when Module C lands. |
| **AC-0-EVT-19** | Module D consumes `ProductReferenceActivated` to enable purchase-order creation against the PR. | §14.5 | AUTO — verified when Module D lands. |

### §5.4 Consumed events from Module K

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-0-EVT-20** | PIM consumes `ProducerActivated` from Module K; enables Product Master activation against that Producer. | §14.5 | AUTO — emit `ProducerActivated`, assert a previously-blocked Product Master is now activatable. |
| **AC-0-EVT-21** | PIM consumes `ProducerRetired` from Module K; blocks new Product Master activations under that Producer. Existing actives preserved per BR-Lifecycle-4. | §14.5; BR-Producer-2 | AUTO — emit `ProducerRetired`, assert new Master activation blocked; assert existing active Masters unchanged. |

---

## §6 Cross-module contracts + boundary respect

PIM is upstream of every commerce-facing module and downstream only of Module K (the Product Master → Producer link). The boundary is enforced by what PIM does NOT carry.

### §6.1 Producer link (upstream from Module K)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-0-XM-1** | Product Master carries a `producer_id` link to Module K's Producer Registry. The Producer entity (legal name, region, KYC status, lifecycle, Discovery-only marker) is owned by Module K; PIM stores only the link. | §3.2 + §15 | AUTO — inspect Product Master schema; assert presence of `producer_id` reference, absence of Producer entity duplication. |
| **AC-0-XM-2** | Product Master cannot be created with a `producer_id` referencing a non-existent Producer. | §3.2 | AUTO — negative path: attempt create with bad reference, assert rejection. |

### §6.2 Bottle Page reads (downstream to Module B)

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-0-XM-3** | PIM has no Bottle-Page-specific entity, event, or governance step. PIM publishes content at Product Master (product-level prose), Product Variant (variant-specific prose), Product Reference (format-specific notes); Module K Producer carries producer-level description. Module B reads these at render time; the Bottle Page render contract is verified in Module B's acceptance doc. | §9.1 + §9.2 | AUTO — inspect PIM entity schemas across all 7 types; assert no Bottle-Page entity/event/governance step on any PIM entity. |
| **AC-0-XM-4** | PIM's translatable content surfaces (Product Master / Product Variant / Product Reference) accept per-locale strings for all six launch locales (EN, IT, FR, DE, JA, zh-Hans); PIM does not enforce locale-completeness as a hard activation gate (partial coverage allowed, §8.3). Bottle Page render-time behaviour is verified in Module B's acceptance doc. | §8 + §9.1; DEC-031 | MIXED — AI fetches per-locale PIM content for a sample PR across all six locales; Paolo confirms readability (rendering assembly verified at Module B acceptance). |

### §6.3 Boundary statements — PIM does NOT carry

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-0-XM-5** | PIM holds NO pricing, commercial-term, or cost attribute. Price / cost / currency live on Module S Offer and Module A Allocation. | §10 + §15 | AUTO — inspect schemas across all 7 types; assert absence of any pricing/cost/currency field. |
| **AC-0-XM-6** | PIM holds NO allocation-state, visibility (CLUB / DISCOVERY), sub-pool, or sourcing-model attribute. The Product Reference is identity-only across all three sourcing models (passive consignment V2, V1, direct purchase — *Direct Purchase deferred at launch, Phase C item I; PIM is sourcing-agnostic regardless*). | §10 + §15 | AUTO — inspect schemas; assert a single PR row is referenced by allocations across all sourcing models without duplication. |
| **AC-0-XM-7** | PIM holds NO serialization, NFC, or NFT attribute. The serialization-type discriminator lives on Module S Offer; NFC tag application + NFT mint live on Module B. *(This silence makes PIM neutral to the D12 on-chain decouple.)* | §11 + §12 + §15 | AUTO — schema inspection across PIM entities; assert absence of any serialization / NFC / NFT field. |
| **AC-0-XM-8** | PIM does NOT recognise Hero Package as a structural type. Hero Package is a Module S Offer-level designation. Crurated v17's `is_club_package` flag on Composite SKU is NOT carried into NewCo PIM. | §3.8 + §15; DEC-019, DEC-061 | AUTO — inspect Composite SKU schema; assert no `is_club_package` / Hero-Package flag. |
| **AC-0-XM-9** | PIM is bottled-wine-only at launch (sole Product Type `WINE`). Liquid Product / Liquid SKU / BottlingResolution are NOT present; Service / Experience SKU subtype is NOT present; **no non-wine Product Type, attribute set, or Format is defined** (generalisation non-goal). | §1 + §3.1 + §15 + §17.1 + §17.2; DEC-065 | AUTO — schema inspection; assert PIM entity types limited to {Product Master, Product Variant, Product Reference, Format, Case Configuration, Sellable SKU Intrinsic, Composite SKU}; assert `WINE` is the only Product Type value; no Liquid / Service / Experience / non-wine types present. **See AC-0-GEN-1 / GEN-8.** |
| **AC-0-XM-10** | PIM does NOT validate Composite SKU producer composition. Surface-asymmetric admissibility (club single-producer DEC-019; Discovery multi-producer DEC-061, deferred D7) fires at Module S Offer publication. | §3.8 + §15; BR-SKU-5 | AUTO — covered by AC-0-BR-SKU-5. |
| **AC-0-XM-11** | PIM Layer 1 (the Product Variant's possible-case-configurations whitelist) is the only PIM input to layered breakability. The effective rule `effective_unbreakable = Layer 2 (producer, Module A) OR Layer 3 (commercial, Module S)` is computed downstream. No module reads any "is breakable" flag from PIM. The Module S Layer-3 operator-override path (DEC-098 Stage 6.5) is verified in Module S's acceptance doc. | §7 | AUTO — schema inspection: assert the Layer-1 whitelist exists on Product Variant; assert no `is_breakable` field anywhere in PIM; downstream Layer-2 / Layer-3 consumption verified when Module A + Module S land. |
| **AC-0-XM-12** | PIM holds no payment-timing distinction, settlement-cadence reference, or financial-event-recording attribute. These belong to Module A, Module S, and Module E (per DEC-072, Module E records events of payment / title transfer / settlement; the accounting integration decides GL treatment). | §10 + §15; DEC-072 | AUTO — inspect schemas across all 7 types; assert absence of any payment-timing, settlement-cadence, GL-treatment, invoicing, or financial-event-recording attribute. |

### §6.4 Generalisation guardrails & the non-behavioural guarantee **(NEW — v0.3-MVP)**

The brief §6 definition-of-done checklist + the three ratified Qs. These verify the **one substantive change** (Wine → Product spine) is correct and **non-behavioural for wine**.

| AC ID | Statement | Anchor | Verification |
|---|---|---|---|
| **AC-0-GEN-1** | **Product Type is a first-class classifier** carried by Product Master; the **sole launch value is `WINE`.** Product Type drives the attribute set, the variant axis, the enrichment adapter, the admissible Format vocabulary, and the identity-uniqueness key. | PRD §3.1; brief §6 | AUTO — inspect Product Master schema, assert a Product Type classifier exists; assert `WINE` is the only defined value; assert the five type-driven selections key off Product Type. |
| **AC-0-GEN-2** | **Core Product entities (Master / Variant / Reference) carry only category-neutral identity fields** (producer link, product name, lifecycle state, variant-identifier handle, Format link, audit/version); **all wine-specific descriptive/identity attributes** (appellation/region, vintage, varietal, scores, tasting notes, drinking window) **belong to the `WINE` attribute set**, not as columns on the core entity. | PRD §3.9; brief §6 | AUTO — inspect the core entity surface; assert wine-specific attributes are modelled in the `WINE` attribute set (not on the neutral core). *(Physical representation is the dev team's call per DEC-073; the criterion verifies the modelling boundary, not the storage shape.)* |
| **AC-0-GEN-3** | **The variant identifier is expressed type-neutrally on the core Product Variant entity**; `WINE`'s vintage (year or NV marker) lives in the `WINE` attribute set. | PRD §3.3 + §3.9; brief §6 | AUTO — assert the core Variant entity does not hard-name a wine-only "vintage" dimension; assert the `WINE` attribute set carries `vintage_year` / NV marker; assert wine variant behaviour is unchanged. |
| **AC-0-GEN-4** | **Enrichment is a pluggable adapter selected by Product Type**; `WINE` = the LWIN/Liv-ex adapter; the **manual fallback is the type-agnostic baseline** and the **launch-critical path (Q3 manual-first)**. A type without an adapter creates via the manual baseline with full validation + dedup. | PRD §5; brief §6; cut-sheet Q3 | AUTO — assert the creation workflow selects an adapter by Product Type and falls back to the manual baseline; assert the manual baseline is a full-fidelity creation path. **Adapter-path behaviour verified when the WINE/LWIN adapter lands (§0.1).** |
| **AC-0-GEN-5** | **Identity uniqueness is a type-defined key**; `WINE` = producer + product name + appellation (unchanged value/behaviour). | PRD §13.1; brief §6 | AUTO — covered by AC-0-BR-Identity-1 (expressed as a type-defined key). |
| **AC-0-GEN-6** | **Lifecycle events are renamed `Wine*`/`BottleReference*` → `Product*`** (`ProductMaster*`, `ProductVariant*`, `ProductReference*`); `SellableSKU*`, `CompositeSKU*`, `Format*`, `CaseConfiguration*`, `EnrichmentDataUpdated` unchanged; **payload semantics identical.** | PRD §14; brief §6 | AUTO — inspect the event registry; assert the renamed families are present with no `Wine*`/`BottleReference*` names remaining; assert payload schemas are unchanged from v1.1 semantics. (Covered for emission by AC-0-EVT-1..3.) |
| **AC-0-GEN-7** | **Areas explicitly UNCHANGED by the generalisation** — the 4-state lifecycle + approval + cascade + rejection (§4); i18n (§8); layered breakability Layer 1 (§7); the sourcing / NFT / serialization boundaries (§10–§12) — behave identically. | PRD §16; brief §3.8 | AUTO — these are covered by their own §2–§6 criteria, which pass identically to v0.1; this row asserts no regression in the unchanged areas. |
| **AC-0-GEN-8** | **No non-wine Product Type, attribute set, or Format vocabulary is defined at launch**, and **no dynamic EAV / rules engine** is built — the ceiling is a neutral core + additive per-type attribute sets. | PRD §16; brief §4 | AUTO — assert only the `WINE` type/attribute-set/Format-vocabulary is populated (covered for type-set by AC-0-XM-9); assert the modelling is a neutral core + additive per-type set, not an open-ended EAV engine. |
| **AC-0-GEN-9** | **Non-behavioural-for-wine guarantee** — for the `WINE` Product Type, **every rule, lifecycle transition, validation, dedup check, enrichment flow, and event payload behaves identically to v1.1.** The change is purely structural naming + attribute placement. | PRD §16; brief §6 | **MIXED** — AI assembles the parity evidence (the renamed v0.1 wine-behaviour criteria all PASS identically; a diff showing only names/attribute-placement changed); **Paolo confirms** the non-behavioural-for-wine proof against the brief §6 checklist. *(This is the safety property that lets the hardened v1.1 catalog spine be generalised inside the launch build.)* |
| **AC-0-GEN-10** | **Naming-cascade source of truth** — Module 0 lands the canonical names (Product Master / Variant / Reference; `Product*` events); the **carve-outs are documented** (Module E keeps category-neutral names; Modules B/C keep physical-unit / wine-display names incl. "Bottle Page"; "Bottle Reference" retained everywhere as a wine-display alias). | PRD §18; Phase C item A | AUTO (PIM-side) — assert the canonical names are landed in Module 0's surface + the §18 table is present; **the cross-module cascade application is verified as each sibling v0.3-MVP module lands** (naming/contract only, zero behaviour change). |
| **AC-0-GEN-11** | **Composite SKU KEPT (Q1)** — single-producer bundles/verticals are sellable at launch; the **N-constituent, producer-agnostic structure is retained as the D7 seam** for the deferred multi-producer Discovery composites (which restore additively, no PIM rework). | PRD §3.8; cut-sheet Q1 | AUTO — assert PIM accepts an N-constituent (N≥2) single-producer Composite SKU at launch and a multi-producer one structurally (the seam); the multi-producer *surface* admissibility is Module S's (deferred D7 → roadmap). Covered for lifecycle by AC-0-J-5 + AC-0-BR-SKU-2..5. |
| **AC-0-GEN-12** | **Approval role-count is admin-configurable (Q2)** — the workflow runs at the configured depth (e.g. a 2-step Creator → Approver for the small launch team) with **no self-approval, distinct actors on every configured step, and full audit** — no spec change. | PRD §4.2; cut-sheet Q2; `feedback_prd_rr_approval` | AUTO — configure a 2-step workflow; assert a valid 2-step approval succeeds; assert self-approval is rejected at any depth; assert each step is audited with actor + timestamp + decision. |

---

## §7 Out of scope for this acceptance pass

- **Engineering Definition of Done** (DEC-073): coverage thresholds, performance budgets, error-handling exhaustion, observability, retry/idempotency mechanics, schema design (column types, FK declarations, nullability as constraints), API style + transport, deployment topology. *(The generalisation's physical representation — typed tables vs JSON attribute bag vs EAV — is explicitly the dev team's call, DEC-073; §6.4 verifies the modelling boundary, not the storage shape.)*
- **UI / UX acceptance**: Admin Panel form layouts, navigation, validation copy, accessibility, responsive design, UI-chrome i18n (distinct from PIM content i18n). A separate UX track owns these.
- **Operational R&R / approval-tier policy** (`feedback_prd_rr_approval`): which named individual approves what; single-approver vs committee; tiered authority by entity type — admin-configurable; the **role-count** is verified only as separation-of-duties (AC-0-GEN-12 / AC-0-BR-Lifecycle-1), not as a policy.
- **Non-functional concerns not anchored to a BR / DEC** at PRD level: latency, throughput, alerting thresholds, infrastructure. Functional-only scope.
- **Post-launch deferrals (PRD §17), carried verbatim to the roadmap** ([`../04-roadmap/Post_Launch_Roadmap_v0.1.md`](../04-roadmap/Post_Launch_Roadmap_v0.1.md)): Liquid Product / Liquid SKU / BottlingResolution (§17.2), Service / Experience SKU subtype (§17.1), separate translation registry / per-translator workflows (§17.3), locale-set expansion beyond six (§17.4), producer-content versioning beyond the standard approval (§17.5), bulk-import auto-replay queue (§17.6). **Their acceptance criteria move to the roadmap with the features; no launch criterion is removed.**
- **The category-expansion forward target** (defining a second Product Type + the A/B/C/D/E + business-model operating-model work to *sell and fulfil* it): a roadmap deliverable, not launch acceptance. The generalisation ships the *catalogue-side* readiness (verified in §6.4); the second-type buildout is post-launch.
- **Cross-module behaviours owned by other modules**: Module S Offer-publication validation, Module A Allocation creation, Module B SerializedBottle / NFC / NFT mechanics, Module C fulfilment holds, Module D PO creation — verified in the receiving module's acceptance doc.

---

## §8 Sign-off log

### §8.1 Format-validation milestones

| Milestone | Date | Notes |
|---|---|---|
| v0.1 PAOLO-VALIDATED (template) | 2026-05-15 | 88 criteria (86 AUTO / 1 MIXED / 1 HUMAN); four format conventions locked; propagated to the 7 siblings. |
| **v0.3-MVP re-cut (Phase D)** | **2026-06-07** | **RATIFIED by Paolo 2026-06-07.** Re-cut from the PAOLO-VALIDATED v0.1 per cut-sheet §5: naming cascade applied to all criteria; re-anchored to the v0.3-MVP PRD; BR-Identity-1 + enrichment generalised (Q3 manual-first posture, §0.1 — the LWIN-adapter-criteria-not-launch-blocking scheduling **confirmed by Paolo 2026-06-07**); **§6.4 added** (12 generalisation criteria). **~100 total (97 AUTO / 2 MIXED / 1 HUMAN).** No criterion removed (KEEP-in-full). |

### §8.2 Per-AC delivery sign-off

Populated at first delivery review. Each criterion's state (OPEN / DEMOED / ACCEPTED) + Paolo's signature + date land here.

| AC ID | State | Date | Paolo signature | Notes / evidence reference |
|---|---|---|---|---|
| AC-0-J-1 | OPEN | — | — | — |
| ... | ... | ... | ... | ... |

(Full table populated at delivery; placeholder rows omitted in this draft.)

---

## §9 Cross-references

- **Spec source (this validates against)** — [`../02-prd/Module_0_PRD_v0.3-MVP.md`](../02-prd/Module_0_PRD_v0.3-MVP.md).
- **Predecessor (re-cut from)** — [`../../reference/v1.1/01-prd/Module_0_Acceptance_v0.1.md`](../../reference/v1.1/01-prd/Module_0_Acceptance_v0.1.md) (PAOLO-VALIDATED 2026-05-15; frozen).
- **Cut-sheet (the delta spec)** — [`../01-triage/Module_0_CutSheet_v0.1.md`](../01-triage/Module_0_CutSheet_v0.1.md) §5 (acceptance delta), §6 (the three ratified Qs).
- **Generalisation brief** — [`../../reference/v1.1/11-category-expansion/Module_0_Generalisation_Change_Brief_v0.1.md`](../../reference/v1.1/11-category-expansion/Module_0_Generalisation_Change_Brief_v0.1.md) §6 (the DoD checklist verified in §6.4).
- **Phase C reconciliation** — [`../01-triage/Phase_C_Reconciliation_v0.1.md`](../01-triage/Phase_C_Reconciliation_v0.1.md) item A (naming cascade), §6 (the floor chains Module 0 feeds: KYC gate, Layer-1 breakability, audit/version-immutability).
- **Roadmap (deferred-feature acceptance moves here)** — [`../04-roadmap/Post_Launch_Roadmap_v0.1.md`](../04-roadmap/Post_Launch_Roadmap_v0.1.md).
- **Sibling v0.3-MVP acceptance docs** (written alongside their PRDs in Phase D) — Module K, A, D, S, B, C, E, + the Admin-Panel PRD's acceptance.

---

*End of Module 0 Acceptance Criteria v0.3-MVP — Phase D re-baseline. Re-cut from the PAOLO-VALIDATED v0.1: naming cascade applied, re-anchored, BR-Identity-1 + enrichment generalised (Q3 manual-first), §6.4 generalisation guardrails added. **Nothing removed (KEEP-in-full).** ~100 criteria (97 AUTO / 2 MIXED / 1 HUMAN). **RATIFIED by Paolo 2026-06-07.** Held in `mvp/`; nothing promoted to `handoff/` until Phase E.*
