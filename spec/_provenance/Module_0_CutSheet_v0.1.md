# Module 0 (PIM) — MVP Cut-Sheet v0.1

- **Version**: v0.1 (**RATIFIED by Paolo 2026-06-07**). **First cut-sheet → doubles as the template format.**
- **Date**: 2026-06-05 (ratified 2026-06-07)
- **Status**: RATIFIED — Phase B triage complete for Module 0 (Q1/Q2/Q3 resolved §6)
- **Owner**: Paolo
- **Inputs**: [`../../reference/v1.1/01-prd/Module_0_PRD_v0.2.md`](../../reference/v1.1/01-prd/Module_0_PRD_v0.2.md) (source spec) · [`../../reference/v1.1/11-category-expansion/Module_0_Generalisation_Change_Brief_v0.1.md`](../../reference/v1.1/11-category-expansion/Module_0_Generalisation_Change_Brief_v0.1.md) (generalisation) · [`../00-method/Dials_Worksheet_v0.1.md`](../00-method/Dials_Worksheet_v0.1.md) (locked dials) · [`../00-method/MVP_Restructure_Plan_v0.1.md`](../00-method/MVP_Restructure_Plan_v0.1.md) (method + P1/P2).
- **Triage tags**: KEEP · SIMPLIFY · DEFER · DROP · **GENERALISE** (Module-0-specific: keep behaviour, restructure names/attributes per the brief).

---

## §1 Verdict & counts

**Module 0 is the foundational, identity-only catalog spine — it is KEPT IN FULL.** It is already lean (bottled-wine-only; Liquid Product, Service/Experience SKUs, and a separate translation registry all already deferred in v1.1 §16). The launch business loop cannot run without the catalog, so there is essentially nothing to defer here that v1.1 hasn't already deferred.

The **one substantive change is the generalisation** (Wine → generic Product spine) — which *adds* a small, deliberate amount of structural work rather than cutting, because it is near-free now and the enabler for the immediate post-launch goal (category expansion). It also triggers a **naming cascade** across the other module PRDs (executed as we strip each).

| Tag | Count | Notes |
|---|---:|---|
| KEEP | ~all | The catalog spine: entities, lifecycle, LWIN, bulk import, breakability L1, i18n, events, boundaries. |
| GENERALISE | 1 workstream | The §3 restructure (Wine→Product, Product Type, attribute placement, adapter, event rename). |
| SIMPLIFY | 0 (build) | Approval *role-count* is admin-configurable, so a small launch team needs no spec change (§2). |
| DEFER (net-new) | 0 | All obvious defers already made in v1.1 §16; carried into the roadmap unchanged. |
| DROP | 0 | — |

**Calibration takeaway:** a Lean MVP does **not** gut foundational modules. Module 0 stays whole; the time savings come downstream (Module S commerce, Module B/C inventory & fulfilment, Module E finance). Expect Module K to look similar; the real cutting starts at A/D and intensifies through S/B/C/E.

---

## §2 Feature inventory & triage

| # | Capability (PRD §) | v1.1 behaviour | Tag | Rationale / forward-compat seam (P1) | Cross-module deps |
|---|---|---|---|---|---|
| 0.1 | Entity hierarchy — Master / Variant / Reference (§3.1–3.3) | Wine Master → Wine Variant → Bottle Reference (= Variant + Format) | **KEEP + GENERALISE** | The universal product key. Rename to Product Master/Variant/**Reference (PR)** per brief §3.1; behaviour identical for wine. | The PR is read by **every** module — see §4 cascade. |
| 0.2 | Format + Case Configuration (§3.4–3.5) | Standalone reference entities; Case Config carries no breakability flag | **KEEP** | Generic already (Format = size of atomic unit). Brief keeps both names. | Module A/S (breakability layers), Module B (stock), Module C (pack). |
| 0.3 | Sellable SKU (Intrinsic) (§3.6) | 1 BR + 1 Case Config + commercial attrs | **KEEP** | Core commercial unit. Definition restated on PR. | Module S (Offer references SKU). |
| 0.4 | Composite SKU (§3.7) | Bundle of ≥2 constituent BRs; producer-agnostic at PIM | **KEEP** | Cheap at PIM (registration + lifecycle only); enables single-producer bundles/verticals at launch **and** is the forward-compat seam for deferred multi-producer Discovery composites (D7). The heavy atomic-bind logic lives in A/B/S. | Module S owns surface-asymmetric admissibility (D7: single-producer at launch). |
| 0.5 | 4-state lifecycle + 3-step approval (§4.1–4.4) | draft→reviewed→active→retired; Creator→Reviewer→Approver (3 distinct people) | **KEEP** | Load-bearing data-quality control every consumer relies on. **Role-count is admin-configurable** (`feedback_prd_rr_approval`) — a small launch catalog team can run a lighter approval (e.g. 2-step) by configuration, **no spec change**. See Q2. | Activation/retirement cascade is the cross-module contract. |
| 0.6 | Cascade rules + rejection + cascade-retirement ordering (§4.5–4.8) | Parent-before-child activation/retirement; in-place rejection; version-immutability + audit | **KEEP** | Behaviour-agnostic; audit is compliance floor. | Parent-before-child emission order consumed by S/A/B/C/D. |
| 0.7 | LWIN-first creation + manual fallback + resilience + capture-then-own (§5) | Liv-ex API auto-populate; manual fallback; producer activation gate; capture-then-own snapshot | **KEEP + GENERALISE** | High ops value, low incremental cost, **already in the Module 0 build now**. Reframe as the **WINE enrichment adapter behind a pluggable interface** (brief §3.5); manual fallback is the type-agnostic baseline = the seam for future categories. **Vendor note:** Liv-ex is an external dependency; the manual path is the fallback if Liv-ex onboarding lags (see Q3). | Module K (producer match/gate). |
| 0.8 | Bulk import (§6) | Configurable depth; partial-failure skip+log; operator-driven re-attempt; **no batch-approval**; no auto-replay | **KEEP** | Needed to **seed the launch catalog** (legacy migration + producer onboarding). Already lean — auto-replay queue already deferred (§16.6). | — |
| 0.9 | Layered breakability — Layer 1 (§7) | Wine Variant possible-case-configs whitelist; default permissive | **KEEP** | Cheap declarative whitelist; Layers 2/3 (Module A/S) depend on it. | Module A (L2 producer breakability), Module S (L3 commercial). |
| 0.10 | i18n — 6 locales on-entity (§8) | Translatable content on the entities; no separate registry | **KEEP** | D2 locked: keep (i18n infra reportedly built — *✱confirm*). Adding locales later is config (§16.4 seam). | Bottle Page (Module B) renders locales. |
| 0.11 | Bottle Page content feeding (§9) | PIM publishes content surfaces; Module B reads at render | **KEEP** | PIM is read-only here; no Module 0 change. NFT provenance content is Module B's (D12 decoupled) — Module 0 already silent. | Module B (renders), Module K (producer prose). |
| 0.12 | Boundaries — sourcing / NFT / serialization (§10–12) | PIM **silent** on all three | **KEEP** | These deliberate silences are exactly what make Module 0 neutral to NFT-decoupling (D12), procurement-variant cuts (D11), etc. Leave intact. | A/B/S/D own the commercial state. |
| 0.13 | Business rules — 25 BRs (§13) | 7 groups: identity, lifecycle, audit, producer, SKU, refdata, bulk, resilience, contract | **KEEP + GENERALISE** | Behaviour unchanged for wine. Generalise the uniqueness rule (BR-Identity-1) to a **type-defined identity key** (brief §3.6). | Event contract (§13.9). |
| 0.14 | Domain events — 22 (§14) | 7 entities × {Created/Activated/Retired} + EnrichmentDataUpdated; parent-before-child ordering | **KEEP + GENERALISE** | Rename `Wine* → Product*` (brief §3.7); payload semantics **identical**. | The cross-module event vocabulary — see §4. |
| 0.15 | Already-deferred set (§16) | Service/Experience SKU; Liquid Product; separate translation registry; bulk auto-replay; producer-content versioning; locale expansion | **DEFER (unchanged)** | All already deferred with clean re-introduction hooks documented. Carry verbatim into `04-roadmap/`. | — |

---

## §3 Generalisation workstream (the one change to Module 0)

Executed as part of the Module 0 MVP PRD (`02-prd/Module_0_PRD_v0.3-MVP.md`), per the change brief. **Non-behavioural for wine** — purely structural naming + attribute placement.

1. **Entity rename** → Product Master / Product Variant / **Product Reference (PR)**; "Bottle Reference/BR" retained only as a wine display alias.
2. **Product Type** added as a first-class classifier; sole launch value `WINE`. Drives attribute set, variant axis, enrichment adapter, identity key, admissible Formats.
3. **Attribute placement** → core entities carry only category-neutral identity fields; all wine-specific attributes (appellation, vintage, varietal, scores, tasting notes…) belong to the `WINE` attribute set. (Physical representation is the dev team's call — out of scope.)
4. **Variant axis** generalised → core entity expresses the variant identifier type-neutrally; `WINE` = vintage (unchanged).
5. **Enrichment as pluggable adapter** → LWIN reframed as the `WINE` adapter; manual fallback = type-agnostic baseline.
6. **Identity uniqueness** → type-defined key; `WINE` = producer + name + appellation (unchanged).
7. **Event rename** → `Wine* → Product*`; payloads identical.
8. **Guardrails** → no non-wine type defined; no dynamic EAV engine; no wine behaviour change; wine-facing UI labels stay "wine/bottle/vintage."

---

## §4 Cross-module ripple (for Phase C reconciliation)

- **The naming cascade is the principal ripple.** `Bottle Reference → Product Reference` and `Wine* → Product*` events appear in **every** module PRD + Architecture. It is **naming/contract only — zero consumer behaviour change** (every event carries the same business signal; BR and PR denote the same key).
- **Execution:** land Module 0 v0.3-MVP names first (source of truth), then apply the cascade to each module's MVP PRD as we strip it (A/D → S → B/C → E) and to Architecture. This is why the coherent re-baseline (no piecemeal handoff) matters — the contract stays internally consistent.
- **No cut here orphans a downstream consumer** (Module 0 keeps everything its consumers read: PR activation events, SKU events, breakability L1, enrichment).

---

## §5 Acceptance-criteria delta

Module 0's acceptance doc is the one already **PAOLO-VALIDATED** (2026-05-15). Because Module 0 scope is **unchanged** (KEEP-in-full), the MVP acceptance doc needs only:
- the **naming cascade** applied (BR→PR, Wine*→Product* in the criteria), and
- a small set of **new criteria for the generalisation guardrails** (Product Type present; core entities category-neutral; non-behavioural-for-wine proof per brief §6 checklist).

No criteria are removed (nothing cut). This is the lightest acceptance delta of any module — a good place to confirm the re-cut method.

---

## §6 Open questions for Paolo (ratification) — RESOLVED 2026-06-07

- **Q1 — Composite SKU at launch.** Recommend **KEEP** (cheap at PIM, enables single-producer bundles/verticals, and is the seam for deferred multi-producer Discovery composites). Confirm launch wants at least single-producer bundles? Or defer Composite SKU entirely (sell single BRs only) if no bundles at launch?
  - **✅ Ratified: KEEP.** Single-producer bundles/verticals are sellable at launch; Composite SKU retained at PIM as the D7 seam for deferred multi-producer composites.
- **Q2 — Approval role-count.** The 3-step workflow needs ≥3 distinct catalog staff per entity. Keep 3-step, or configure a lighter approval (e.g. 2-step Creator→Approver) for the small launch catalog team? (Operational config — no spec change either way.)
  - **✅ Ratified: lighter approval acceptable** — admin-configurable, **no spec change**. The small launch catalog team may run a 2-step (Creator→Approver) by configuration. (Same decision as Module K Q3.)
- **Q3 — Liv-ex/LWIN vendor readiness.** Is the Liv-ex API integration contracted and ready for launch, or do we lean manual-first (the fallback path) and wire LWIN when the vendor lands? Affects whether enrichment is on the launch critical path.
  - **✅ Ratified: manual-first baseline; LWIN open for launch or post-launch.** Liv-ex integration is *in progress* (may or may not land by launch). Plan **manual-first** so catalog enrichment is **off the launch critical path**; the pluggable WINE-enrichment adapter (generalisation §3.5) lets LWIN drop in when the vendor is ready — at launch if it lands in time, otherwise post-launch, with no rework.

---

*End of Module 0 Cut-Sheet v0.1 — template instance; **RATIFIED by Paolo 2026-06-07** (Q1 KEEP Composite SKU · Q2 lighter approval OK, no spec change · Q3 manual-first, LWIN open). Verdict: KEEP-in-full + generalise; ~0 net-new deferrals.*
