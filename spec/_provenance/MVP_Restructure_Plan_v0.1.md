# NewCo ERP — Launch-MVP Re-scoping Plan v0.1

- **Version**: v0.1 (DRAFT — plan agreed on the three governing decisions 2026-06-05; awaiting dials workshop)
- **Date**: 2026-06-05
- **Status**: OPEN — Phase A (frame & dials) in progress
- **Owner**: Paolo
- **Predecessors / inputs**:
  - `greenfield/` v1.1 baseline (released 2026-05-09) — the full spec being stripped. **FROZEN**; never edited by this exercise.
  - [`Build_Workplan_v0.1.md`](../../reference/v1.1/09-build-readiness/Build_Workplan_v0.1.md) — the 9-phase build the dev-team estimate derives from.
  - [`Module_0_Generalisation_Change_Brief_v0.1.md`](../../reference/v1.1/11-category-expansion/Module_0_Generalisation_Change_Brief_v0.1.md) — folded into this exercise (see §6).
  - Acceptance-criteria workstream (Module 0 PAOLO-VALIDATED; 7 siblings drafted, awaiting review) — re-scoped under this exercise (see §6).
- **Methodology**: product-spec layer per DEC-073 (no tech-implementation decisions); self-contained per DEC-074.

---

## §1 Objective & constraint

Strip the v1.1 spec to a **Lean launch MVP** so the platform can go live earlier than the tech team's current estimate (~25–35% calendar cut). **Defer — not delete** — non-essential scope to a post-launch roadmap. The exercise is a re-baseline of the *specification*; it is paired with a dev-team sizing exercise (§7 Phase E) to confirm the cut actually buys the months.

## §2 Three governing decisions (locked 2026-06-05)

1. **Cut depth: Lean MVP.** Default is aggressive defer + manual-first operations where safe. KEEP only what the launch business loop or the compliance/data-integrity floor requires.
2. **Method: one coherent re-baseline, then a single handoff.** Strip and cross-module-reconcile the *whole* MVP before promoting anything to the tech team — to avoid stripping one module in a way that silently breaks another. **No piecemeal handoff.**
3. **Governance: high-touch.** Live dials workshop up front (Paolo decides, Claude recommends); Paolo ratifies each module's cut-sheet (~30 min each).

## §3 Scope floor — what cannot be cut

- KYC, sanctions screening, the unified hold gate (legal).
- Tax-correct invoicing for the launch jurisdiction(s) (legal).
- Two-layer no-overselling guard + committed-inventory protection (data integrity).
- Audit-trail / retention hooks (compliance).
- The **core business loop**: producer onboarded → allocation published → customer registers/KYC'd → browses → buys → pays → bottle received into inventory → shipped → in cellar → (later) supplier settled.

## §4 Triage vocabulary & litmus test

Every capability in every module is tagged:

- **KEEP** — load-bearing for the core loop or the floor.
- **SIMPLIFY** — needed at launch but a lighter form (manual-first / single-currency / simpler algorithm); full version deferred.
- **DEFER** — not needed at launch; moves wholesale to the post-launch roadmap.
- **DROP** — genuinely unnecessary; removed. (Rare.)

**Litmus:** *KEEP only if the core launch loop cannot run without it, or compliance/data-integrity demands it. Otherwise SIMPLIFY (if a lighter form is needed now) or DEFER.*

### §4.1 Two binding principles (locked 2026-06-05)

- **P1 — Defer without burning bridges (forward-compatibility).** No DEFER or SIMPLIFY may force a later rebuild of what we ship. Every cut-sheet entry for a deferred/simplified item must name the **seam** preserved so the post-launch build-back is *additive*, not a rewrite. (Paolo, D5.) Same logic as the Module 0 generalisation: do the cheap structural thing now so expansion is additive later.
- **P2 — Admin-first, self-serve-later.** For **producer-facing and back-office** surfaces, operators drive via the Admin Panel at launch (admin-parity already exists per DEC-083); rich self-serve UI is deferred where the backend capability + admin surface already cover it. **Exempt:** the consumer storefront — customer self-serve browse/buy *is* the core loop. (Paolo, D23.)

## §5 Folder organisation

```
03-NewCo/
├── greenfield/            # FROZEN v1.1 — full spec, untouched (audit/diff anchor)
├── handoff/               # tech-team source of truth; MVP baseline promoted here ONCE, coherent
├── mvp/                   # this exercise
│   ├── 00-method/         # this plan, the litmus test, the locked dials
│   ├── 01-triage/         # per-module cut-sheets: every feature → KEEP/SIMPLIFY/DEFER/DROP + rationale + deps
│   ├── 02-prd/            # stripped MVP PRDs (v0.3-MVP) + Mod 0 generalisation + updated Architecture
│   ├── 03-acceptance/     # MVP-scoped acceptance criteria per module
│   ├── 04-roadmap/        # post-launch roadmap (extends qa.deferred.md with everything we defer)
│   └── 05-release/        # MVP release index + revised build workplan for the dev-team sizing exercise
└── UX-drafts/ , _archive-*/ …
```

`greenfield/` is never edited. Every MVP doc cites its v1.1 predecessor; the post-launch roadmap is the audit trail of what moved and why.

## §6 Two changes folded into this exercise

- **Module 0 multi-category generalisation** — executed *as part of* the Module 0 strip (one pass: generalise **and** strip), per the change brief. Drives the cross-module naming cascade (`Bottle Reference → Product Reference`) inside the MVP PRDs. **Time-sensitive** — see §8 R3.
- **MVP-scoped acceptance criteria** — each module's acceptance doc is re-cut to the *stripped* scope and then validated. Module 0's validated doc is re-trimmed to MVP; the 7 siblings are re-scoped **before** validation (not validated at full v1.1 scope).

## §7 Plan of action — five phases

- **Phase A — Frame & dials (high-touch workshop).** Stand up `mvp/`; lock the litmus test + the macro dials (§9). Output: the "cut constitution."
- **Phase B — Full triage (all 8 modules).** Per-module cut-sheets applying the locked dials, each with explicit cross-module dependency notes. Paolo ratifies each. Drafted in dependency order (0/K → A/D → S → B/C → E) so each builds on settled upstream contracts.
- **Phase C — Cross-module reconciliation (coherence gate).** Verify no KEEP is orphaned (its upstream producer was deferred) and no DEFER orphans a downstream consumer; reconcile the trimmed event contract. **This is the step that discharges the coherence concern.** Output: reconciled MVP scope map.
- **Phase D — Re-baseline.** Write the stripped MVP PRDs (+ Mod 0 generalisation + naming cascade), MVP acceptance criteria, the post-launch roadmap, updated Architecture, MVP release index, revised build workplan.
- **Phase E — Coherent handoff + sizing loop.** Promote the whole MVP baseline to `handoff/` at once; tech team sizes against it; adjust until the launch timeline is credible.

## §8 Risks & mitigations

- **R1 — Cross-module incoherence from stripping.** → Phase C reconciliation gate; dependency notes on every cut-sheet; coherent-handoff discipline (no piecemeal).
- **R2 — Scope cut alone won't hit the date.** → Pair with the dev-team sizing exercise (Phase E); obtain the tech-team estimate **decomposed by module/phase** so the scalpel aims at the heavy modules (S, B, C, E), not the cheap ones.
- **R3 — Module 0 generalisation value is time-sensitive** (cheap only before code/data exist; team builds Module 0 now). → Recommend an interim **build-caution note** to the team (adopt generic Product spine; hold off hard-coding wine-only identity) — a heads-up, not a piecemeal handoff, so it respects the coherent-handoff rule. Paolo to approve.
- **R4 — Un-freezing a hardened baseline.** → `greenfield/` stays frozen; all work in `mvp/`; predecessor citations + the roadmap preserve the audit trail.
- **R5 — Business-model touch-ups.** → Some Lean dials (e.g. defer services, narrow currencies) may touch the BMD; keep BMD edits minimal and explicitly tracked.

## §9 Candidate dials (to lock in the Phase A workshop)

The macro business-scope decisions that drive the bulk of the cut. Many are already deferred in `qa.deferred.md` (the workshop fast-tracks those). Grouped by owning area:

**Commerce & customer (Mod S / K)**
- D1 Currencies supported at launch (count)
- D2 Locales / languages (storefront · bottle page · admin · producer)
- D3 Ship-to geography + customs/excise/DDP scope
- D4 Payment methods (cards · SEPA · saved-card 3-stage escalation)
- D5 Gifting flow
- D6 Cancellation / refund sophistication (14-day window)
- D7 Composite SKUs / Discovery multi-producer offers
- D8 Club / membership richness (multi-profile, club credit, originating-club, Hero Package, stacking)

**Catalog & supply (Mod 0 / A / D)**
- D9 Catalog enrichment (LWIN auto vs manual-first) + bulk import
- D10 Allocation sophistication (sub-pools / visibility partitions)
- D11 Procurement variants (two passive-consignment + direct-purchase)

**Inventory & fulfilment (Mod B / C)**
- D12 NFT / blockchain + NFC tags (digital provenance) at launch
- D13 Late-binding pick algorithm sophistication
- D14 Returns / replacements automation
- D15 Recall coordination automation
- D16 Stocktake / quarantine / inventory-adjustment depth
- D17 Cellar render richness

**Finance (Mod E)**
- D18 Multi-currency dual-recording (tied to D1)
- D19 Supplier settlement automation (quarterly) vs operator-run for first cycles
- D20 Italian SDI tax connector (conditional on incorporation)
- D21 Chargeback automation

**Cross-cutting**
- D22 Services / experiences (already storage-only at launch — confirm)
- D23 Producer Portal reporting depth
- D24 Admin Panel surface depth
- D25 Customer-support tooling (already email + admin-lookup at launch — confirm)

## §10 Open inputs needed from Paolo

- Is the tech team's current estimate **decomposed by module/phase**? (Aims the cut — R2.)
- The docs reference an "auto-memory" system (e.g. `project_newco_acceptance_criteria`) not currently loaded — surface if available; otherwise reconstructed from the docs.
- Approve (or not) the interim **Module 0 build-caution note** (R3).

---

*End of Launch-MVP Re-scoping Plan v0.1 — anchor artefact for the strip-down exercise. Spec baseline unchanged at v1.1; this is a derivative planning artefact.*
