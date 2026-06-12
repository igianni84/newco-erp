---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-12
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-12 12:25 (interactive — foundations-modules-skeleton MERGED + ARCHIVED + PUSHED; F1 1/3 CLOSED)** — Closing ritual (GUIDE §2.7) run interactively at Giovanni's delegation. The ralph loop had stopped after task 3.1 (8/9), leaving the final task **4.1 unrun**; 4.1 was **completed interactively** — scenario→test traceability table recorded in the (now archived) progress.md, five quality commands green in CLAUDE.md order, ZERO new tests (15/15 scenarios already covered) — committed `059c5e6`. **Pre-merge semantic verification: CLEAN** — 0 CRITICAL / 0 WARNING on the implementation; all 6 requirements implemented + tested, every architecture scenario backed by a recorded red-proof. Then **merge --no-ff `dc36dfc`** (branch's 9-commit history preserved) → **push origin main** (`0b65865..dc36dfc`) → **`openspec archive`** (truth spec `openspec/specs/module-architecture/spec.md` created — 6 requirements; change moved to `openspec/changes/archive/2026-06-12-foundations-modules-skeleton/`) → archive commit + push. Branch `ralph/foundations-modules-skeleton` left in place (not deleted).

## Build & Quality Status
- **Stack unchanged** (this change added ZERO deps — composer.json/lock identical to pre-merge main): PHP 8.5.2 · Laravel 13.15.0 (`^13.8`) · Filament v5.6.7 + Livewire v4.3.1 · Pest 4.7.2 incl. pest-plugin-arch v4.0.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev; tests `:memory:`.
- Quality on **main post-merge**: full suite **60/60 (317 assertions)** ✅ · type_check 0 @ level max ✅ · lint ✅. `origin/main == local main` (`0 0`).

## Active Change & Next Task
- **No active change.** F1 1/3 (`foundations-modules-skeleton`) is merged + archived + pushed. Live on main: the nine module skeleton + `App\Modules\Module` registry, the always-on `tests/Architecture/` suite (conformance · boundary-law both directions · persistence convention), and `docs/module-template.md`.
- **Next: author F1 2/3 — `foundations-domain-events-audit`** (event substrate implementing ADR `decisions/2026-06-12-event-substrate-and-audit-store.md`: transactional outbox `domain_events` + `audit_records` + `event_deliveries`, inline delivery at launch; the first `pgsql` CI lane lands with this change's first domain migration). Flow: fresh Claude Code window → `/spec-to-change` (GUIDE §2.3/§2.8) → human review → `APPROVED` → `./ralph.sh`. F1 3/3 after = `foundations-money-i18n-flags`.
- **Optional tidy-up:** the new truth spec `openspec/specs/module-architecture/spec.md` carries a `## Purpose: TBD` placeholder left by the archive tool (invariant 11 → NOT hand-edited; resolve via a future change or with explicit OK).

## Blockers & Decisions Needed
- None blocking.
- **Carry-over (not closed by this change):** human edits to CLAUDE.md from the ADR-1/ADR-2 sessions (if not yet applied); semantic-verify debts W1/W2/W3/S1/S3 from bootstrap (bonify before staging / Module K gate). Open ADR gates: identity/auth (K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (F7) · frontend TanStack (Module S).

## Open Patterns
- The module-build conventions (registry = single source of "the nine"; the three arch-test shapes; the persistence prefix; the doc-pin idiom; the app-file idiom) are now **durably documented in `docs/module-template.md`** and in the archived `openspec/changes/archive/2026-06-12-foundations-modules-skeleton/progress.md` (`## Codebase Patterns`). Read those first when authoring F1 2/3.
- **Registry rule** stands: every conformance/arch/doc-pin test AND `bootstrap/providers.php` iterate `Module::cases()` — the one legitimate hardcoded list is `$platformNamespaces` (the registry's complement).
- **Closing-ritual lesson (this session):** when a ralph loop exits before its last task, the change is NOT at `CHANGE_COMPLETE` even if the commits look done — check the `tasks.md` boxes + the last `progress.md` entry before merging. The final sweep/traceability task finishes cleanly interactively (GUIDE §2.7 "risolvi interattivamente").
