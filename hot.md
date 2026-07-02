---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — Compliance-remediation Round 1 (pre-Paolo). RM-24 reviewed & pushed; Round-1 quick wins done bar RM-06.** Verdict reports in **`docs/validation/`**; live backlog **`Remediation_Tracker.md`**. Driven by Paolo Alfieri's 2026-07-01 mail. **On origin/main:** RM-07 `5b64cc8`, RM-04 `d8ec261`, RM-09+F3 `5eb415d`, RM-10 `04406b8`, RM-24 `4c373af`.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **GREEN:** full suite **1769/1769**, PHPStan/Pint clean.
- Run the full suite via `php -d memory_limit=-1 vendor/bin/pest` — bare `php artisan test` OOMs at 128M (lessons.md 2026-06-20).

## Active Change & Next Task
- No openspec change open (Round-1 items are size-S → coded directly, TDD). `docs/validation/` answers Paolo's 3 asks: verdict (#1), env-readiness (#2), canon delta (#3).
- **Scoreboard vs local baseline:** Module 0 = 67 P / 10 Part / 7 F / 16 Def (100). Module K = 77 / 26 / 19 / 8 (130). ~70% Pass in-scope.
- **Round 1 backlog [`Remediation_Tracker.md`](docs/validation/Remediation_Tracker.md)** (25 items RM-01..25; **read it before touching any Module 0/K fix**; update it + this line as items land).
- **Done & pushed:** RM-07 `5b64cc8` · RM-04 `d8ec261` · RM-09+F3 `5eb415d` · RM-10 `04406b8` · RM-24 `4c373af`.
- **RM-24 ✅ done & pushed `4c373af` — canon DEC-023 / BR-Identity-5:** Product Type immutable post-creation. Explicit `ProductMaster::booted()` `updating` guard (`isDirty('product_type')` → new `ProductTypeImmutable`) — the only path-complete chokepoint (real mutable column + `$guarded=[]`, no update Action, read-only Filament). Fires on UPDATE only (creation free); passes lifecycle transitions. Mini-ADR `2026-07-02-adopt-dec-023-product-type-immutable` (+INDEX; §3 "—"→"mini", canon-DEC absent from spec, like RM-04/10). **Zero behaviour delta** (codifies an already-satisfied invariant); scope = `product_type` only (name re-versioning = RM-14). +2 tests, 1769/1769.
- **Active next:** **RM-06** (PIM reject/edit review-freshness + explicit re-submit, S/M) — last Round-1 item + Paolo's "rejection round". Then Round 2 = floor builds (RM-01 erasure / RM-02 enhanced-KYC).

## Blockers & Decisions Needed
- **Canon drift DEC-007 → DEC-023** (heaviest on Module K). Headline divergences vs code: membership "approved-but-unpaid" flow **DEC-016 declared WRONG** (RM-03); capacity ships **UNCAPPED** vs DEC-017 seat-set (RM-05) — both wait on Modules S/E/A.
- **Floor gaps (do NOT defer, Round 2):** GDPR erasure/anonymisation absent (K J-9/9a/FSM-16; no Address entity — RM-01); enhanced-KYC €10k/€50k threshold absent (seam columns only — RM-02).
- **Rule now firm (lessons.md 2026-07-02):** adopting a canon-DEC absent from frozen `spec/` → ALWAYS a mini-ADR (confirmed 3×: RM-04/10/24); the tracker "ADR?" column is advisory. Applies to RM-03/05/23.

## Open Patterns
- **Escalation-asymmetry (memory `spec-divergence-from-cmless-documentation`) confirmed live:** our frozen handoff carried the wrong membership flow; canon DEC-008..023 never crossed. This validation IS the corrections-inbox — deltas → local ADRs, genuine gaps → c-mless issues.
- **Divergent module maturity:** Catalog enforces SoD + now Product-Type immutability; Parties does NOT enforce SoD (RM-08).
- **Incidental findings open (Tracker §7):** F1 DemoSeeder SQLite-only (PG-truncate); F2 prod operator-mgmt missing → SoD unsatisfiable in prod. Convention: log any incidental discovery in §7 — never drop it.
