---
type: decision
status: active
date: 2026-06-16
---

## Decision: Within-catalog retirement reference-integrity is scoped to the terminal sellable edge

For Module 0 (Catalog) at launch, a **single-entity retirement** is blocked **only** when the entity is referenced by an `active` **terminal sellable object** that has not completed — concretely:

- a **Product Reference** referenced by an `active` **Sellable** or **Composite SKU**, or
- a **Case Configuration** referenced by an `active` **Sellable SKU**.

A **hierarchy parent** is **not** blocked on its children: a single-entity retire of a **Product Master** with `active` Variants, or a **Product Variant** with `active` Product References, **succeeds and preserves** those children (they stay `active`; only *new* activation under the now-`retired` parent is prevented — the activation cascade gate, design D7).

The cross-module downstream-reference leg of BR-Lifecycle-5 (active Allocations, issued vouchers, in-flight orders, SKUs on live Offers) remains a **documented Phase-3 seam** — those referencers do not exist yet. The operator-driven `RetireProductMasterCascade` (parent-before-child, one transaction) is unaffected by this decision and is identical under every option considered.

This is the founder's resolution (2026-06-16) of the `catalog-lifecycle-approval` task 5.2 `HUMAN_NEEDED` escalation: **Option B**.

## Context: why this came up

The `catalog-lifecycle-approval` change's delta spec (`openspec/changes/catalog-lifecycle-approval/specs/product-catalog/spec.md`, Requirement *Retirement Cascade and Reference Integrity*) was **internally contradictory** for the Master→Variant relationship:

- its normative block paragraph gave **"a Product Master with `active` Variants"** as an example of a single-entity retire that must be **blocked**;
- its own scenario *"Retiring a parent preserves existing active children"* says retiring an `active` Master with an `active` Variant **succeeds**, and the Variant **stays `active`**.

These are mutually exclusive: if a Master with active Variants cannot be single-retired, the only remaining path is the operator cascade (which retires the Variant too), so "child stays active" becomes unreachable within catalog.

The block example also **diverged from the immutable PRD** (`spec/02-prd/Module_0_PRD_v0.3-MVP.md`):

- **§4.5 / BR-Lifecycle-4** (lines 222, 425): retiring a parent **preserves** existing active children — never blocks on them.
- **§4.6 / BR-Lifecycle-5** (lines 226, 427): the retirement block is over *"active downstream references that have not yet completed"* — the PRD's only examples are **cross-module commercial** objects (*open Offers actively selling, allocations still serving orders, vouchers still pending fulfilment*), all of which are Phase-3 and do not exist at launch.

So the immutable PRD never asks for a within-catalog "parent with active children" block; the delta spec's example over-extended §4.6 onto a hierarchy edge that §4.5 explicitly preserves. The Catalog spine cannot reach `active` children without the spine being live, but at launch the **only** within-catalog reference that resembles a "downstream reference" is the SKU → PR/CaseConfig edge (the SKU is the terminal sellable object).

The cascade half (`RetireProductMasterCascade`) was never blocked and is unchanged.

## Alternatives considered

- **A — No within-catalog block at launch (the most PRD-literal reading).** §4.6/BR-Lifecycle-5 is purely cross-module commercial → defer the entire reference-integrity guard to the Phase-3 referencer changes. Within catalog, only §4.5 *preserve* applies; every single-entity retire succeeds and preserves children/references. Single-entity `Retire*` Actions get **no** guard.
- **B — Block only the terminal sellable edge (CHOSEN).** Preserve hierarchy parents (Master→Variant, Variant→PR); block a single-entity retire of a PR / Case Configuration that an `active` Sellable/Composite SKU still references.
- **C — Symmetric block on every parent-with-active-children** (the inverse of the activation cascade; matches an early reading of design D8 prose). Blocks retiring a Master with `active` Variants, a Variant with `active` PRs, etc.

## Reasoning: why B won

- **It resolves the contradiction with the minimal, faithful correction.** Only one bad example ("a Master with `active` Variants") in the delta spec was wrong; deleting it and keeping the two scenarios (`preserve` for hierarchy, `block` for the PR ← active SKU edge) makes the requirement internally consistent. Both delta-spec scenarios are satisfied as written.
- **It keeps the one launch-meaningful integrity check.** The Sellable/Composite SKU is the terminal sellable object; retiring its referenced PR (or Case Configuration) out from under it would silently orphan something currently sellable. Surfacing "this PR is still referenced by active SKU X — retire/close it first, or use the operator cascade" is the within-catalog realization of the symmetry the PRD §4.6 itself invokes ("the symmetric counterpart of the activation cascade").
- **It does not contradict the immutable PRD.** §4.5 *preserve* governs hierarchy parents (honoured); §4.6's *block* is realized over the launch-available within-catalog reference (the SKU edge), with the cross-module commercial leg left as the documented Phase-3 seam. No edit to `spec/**` or `openspec/specs/**`.
- **C was rejected** because it makes the PRD §4.5 *preserve* behaviour **unreachable** within catalog and would require the delta spec to diverge from the frozen `spec/` baseline (a spec-immutability breach, invariant 11).
- **A was rejected** (though defensible and the most literal) because it drops the only guard that has teeth at launch, allowing an operator to orphan an active sellable SKU with no warning; B costs only a small guard on two Actions to prevent that.

## Trade-offs accepted

- The within-catalog SKU → PR/CaseConfig block is **not a verbatim PRD example** — §4.6's listed references are all cross-module/commercial. B treats the SKU edge as the within-catalog *subset* of "downstream references that have not yet completed." This is an interpretation, recorded here, not a literal PRD quote. (Option A would have been the literal-only path.)
- Two extra guards to build and test (`RetireProductReference`, `RetireCaseConfiguration`); `RetireProductMaster`/`RetireProductVariant` stay guard-free.
- The cross-module leg of BR-Lifecycle-5 remains unenforced until Phase 3 — accepted, and already a recorded seam in the proposal/design.

## References

- `spec/02-prd/Module_0_PRD_v0.3-MVP.md` §4.4–4.7 (lines 208–230), §13.2 BR-Lifecycle-3/4/5 (lines 423–427) — immutable baseline.
- `openspec/changes/catalog-lifecycle-approval/specs/product-catalog/spec.md` — Requirement *Retirement Cascade and Reference Integrity* (the contradiction, now corrected).
- `openspec/changes/catalog-lifecycle-approval/design.md` D7 (activation cascade — within-module parent reads), D8 (this scope).
- `openspec/changes/catalog-lifecycle-approval/tasks.md` task 5.2; `progress.md` [2026-06-16 14:54] full diagnosis.
- CLAUDE.md invariants 3 (committed inventory — the broader committed-state discipline this guards locally), 10 (module boundaries), 11 (spec immutability).
- Acceptance: `spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md` AC-0-FSM-11 (retirement cascade — actives valid, no new children), AC-0-BR-Lifecycle-4.
