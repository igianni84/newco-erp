---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-01
---

# Hot Cache

## Last Updated
**2026-07-01 — Compliance-remediation Round 1 underway (pre-Paolo). RM-07 ✅ shipped & reviewed; RM-04 is next.** The Module 0 & K verdict reports live in **`docs/validation/`**; the live backlog is **`Remediation_Tracker.md`**. RM-07 made the SoD / rejection walkthroughs walkable in the Filament console (3 distinct operator logins + a real-lineage reviewable fixture). Driven by Paolo Alfieri's 2026-07-01 mail. **Pushed to origin/main** — RM-07 `5b64cc8` + validation reports `e38f346`.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **GREEN:** full suite **1761/1761** (+8 from RM-07), PHPStan/Pint clean.
- Run the full suite via `php -d memory_limit=-1 vendor/bin/pest` — bare `php artisan test` OOMs at 128M (lessons.md 2026-06-20).

## Active Change & Next Task
- No openspec change open (RM-07 was size-S → coded directly, TDD). `docs/validation/` answers Paolo's 3 asks: verdict (#1), env-readiness (#2), canon delta (#3).
- **Scoreboard vs local baseline:** Module 0 = 67 P / 10 Part / 7 F / 16 Def (100). Module K = 77 / 26 / 19 / 8 (130). ~70% Pass in-scope.
- **Compliance-remediation Round 1 → backlog [`Remediation_Tracker.md`](docs/validation/Remediation_Tracker.md)** (25 items RM-01..25; **read it before touching any Module 0/K fix**; update it + this line as items land).
- **RM-07 ✅ reviewed & approved (2026-07-01)** (seed ≥2 operators + chain DemoSeeder + real-lineage SoD fixture): new `OperatorDemoSeeder` (3 role-segmented logins) + `DemoSeeder` self-provisions, production-guarded, resets event/audit log. Tests: `OperatorDemoSeederTest` (3) + `DemoSeederTest` (5, incl. console distinct-actor activate + rejection).
- **Active next:** **RM-04** (Hold enum 6→8, **+ mini-ADR adopting DEC-008**) → RM-09 (reconcile erasure ADR) → RM-10 (ClubCredit rename) → RM-24 (Product-Type immutability). All S, Round 1.

## Blockers & Decisions Needed
- **Canon drift is DEC-007 → DEC-023** (16 new), heaviest on Module K. Three headline divergences vs code: (1) membership "approved-but-unpaid" flow **DEC-016 declared WRONG**; (2) capacity ships **UNCAPPED** vs DEC-017 seat-set; (3) `HoldType` enum = **6** vs DEC-008 = **8** (RM-04).
- **Floor gaps (do NOT defer):** GDPR erasure/anonymisation absent (K J-9/9a/FSM-16; no Address entity — RM-01); enhanced-KYC €10k/€50k threshold absent (seam columns only — RM-02).

## Open Patterns
- **Escalation-asymmetry (memory `spec-divergence-from-cmless-documentation`) confirmed live:** our frozen handoff carried the wrong membership flow; our findings + canon's DEC-008..023 never crossed. This validation IS the corrections-inbox — deltas → local ADRs, genuine gaps → c-mless issues.
- **Divergent module maturity:** Catalog enforces SoD (self-approval blocked, console-tested); Parties does NOT (RM-08).
- **DemoSeeder now self-provisions** (chains RoleSeeder + OperatorDemoSeeder, production-guarded, resets event/audit log): one `db:seed --class=Database\Seeders\DemoSeeder` yields roles + 3 operator logins + full demo data + the real-lineage SoD fixture ("Échézeaux Grand Cru", reviewed, under active DRC). **Caveat (pre-existing, not RM-07): the seeder is SQLite-only** — `reset()` truncates FK-referenced tables one-by-one, which PG rejects (Laravel's PG `withoutForeignKeyConstraints` = `SET CONSTRAINTS DEFERRED`, no help for TRUNCATE). Fine — dev/demo run on SQLite; a future PG demo env would need `TRUNCATE … CASCADE`.
