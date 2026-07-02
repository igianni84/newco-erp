# Progress — catalog-review-freshness-resubmit

## Codebase Patterns
(consolidated reusable patterns — read first each iteration)

- **Canon-DEC adoption → always a mini-ADR** (`lessons.md` 2026-07-02, confirmed 3× via RM-04/10/24). Mirror the newest exemplar `decisions/2026-07-02-adopt-dec-023-product-type-immutable.md` (frontmatter `type: decision`/`status: active`/`date`; sections Decision / Context / Alternatives / Trade-offs / References; `[[wikilink]]` cross-refs). Add the row to `decisions/INDEX.md` **newest-first** (top of the table). Verify with `openspec validate <change> --strict`.
- **This change's spec anchors (verified 2026-07-02):** truth spec `openspec/specs/product-catalog/spec.md` *Approval Governance* req `:214`, rejection paragraph `:220`, scenario `:240`. Frozen: `AC-0-J-7` (`spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md:78`), `AC-0-BR-Lifecycle-6` (`:133`), `BR-Lifecycle-6` §4.3 (`spec/02-prd/Module_0_PRD_v0.3-MVP.md:429`). Code seam: `LifecycleTransition.php` (`reject()` `:189-214`), `ApprovalGovernance.php` (`guard()`). Shipped test to INVERT: `ProductMasterLifecycleTest.php:363-387` ("rejection is not terminal").
- **⚠️ DEC-019 collision:** canon MVP-DEC-019 = review-freshness (this change); frozen spec's own DEC-019 = unrelated Module-S club single-producer composites (`spec/04-decisions/MVP_Decisions_Register_v0.1.md:50`). Never conflate.

---

## [2026-07-02 10:32] — 1.1 Mini-ADR: adopt canon MVP-DEC-019
- **What:** Wrote the mini-ADR `decisions/2026-07-02-adopt-dec-019-review-freshness-resubmit.md` recording the local adoption of canon MVP-DEC-019 (review-freshness). Contains all four required points: (a) build-completeness toward our own `AC-0-J-7`/`BR-Lifecycle-6` (we were Partial); (b) canon adoption of the enforced review-freshness invariant (explicit re-submit + activation block-gate); (c) the derive-from-audit vs literal-"flag" interpretation (design D3); (d) the edit-re-arms leg deferred to RM-14. Plus the ⚠️ DEC-019 number-collision note and the honest "fourth-class blend" nuance (build-completeness + behavioural inversion, unlike zero-behaviour RM-24). Added the newest-first row to `decisions/INDEX.md` linking RM-04/10/24 precedents.
- **Files changed:** `decisions/2026-07-02-adopt-dec-019-review-freshness-resubmit.md` (new), `decisions/INDEX.md` (row).
- **Quality loop: green.** Doc-only (no code). `openspec validate catalog-review-freshness-resubmit --strict` → valid; INDEX grep → 1 hit for the new filename; Pint `passed`. Full Pest/PHPStan not run — zero PHP touched (suite was 1769/1769 at baseline); the task's own test hint scopes verification to grep + `openspec validate`.
- **Learnings for future iterations:**
  - Every citation in the ADR was verified against the live tree before writing (spec line numbers, validation lines, precedent ADR filenames, the DEC-019 collision) — don't trust design.md's line refs blindly, but they matched.
  - The next task (1.2) adds exception factories + `catalog.lifecycle.*` localized keys and starts touching PHP — from here the full quality loop (Pest + PHPStan + Pint via `php -d memory_limit=-1 vendor/bin/{pest,phpstan analyse}`) applies.
---
