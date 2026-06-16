---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-16
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-16 (interactive — authored OpenSpec change `catalog-lifecycle-approval` via `/spec-to-change`).** Full grounding from spec/PRD/acceptance + code recon; founder chose FULL scope (approval governance IN + all 7 spine entities + cascades). 4 artifacts written, `openspec validate --strict` GREEN (8 deltas: 7 ADDED + 1 MODIFIED on `product-catalog`; 17 tasks). **Approved by Giovanni; APPROVED marker + ralph launch pending** (human creates APPROVED; preflight auto-commits the change folder).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **As of last close (`parties-producer-lifecycle`): full suite 475/475 on SQLite AND PG17, phpstan max 0, pint clean.** `catalog-lifecycle-approval` is authored but **not yet implemented** (0/17 tasks) — its tests land via ralph.

## Active Change & Next Task
- **`catalog-lifecycle-approval` — authored + approved, 0/17 tasks.** Module 0 (Catalog) lifecycle: the 4-state FSM `draft→reviewed→active→retired` across **all 7 spine entities** + Creator→Reviewer→Approver governance (separation-of-duties, role-count, rejection) + the Producer activation gate + the **codebase's first cross-module event consumer** (`ProducerActivated`/`ProducerRetired` → `catalog_producer_states` projection → gate) + activation/retirement cascades + 14 `*Activated`/`*Retired` events. One migration only (`catalog_producer_states`).
- **Next: human runs** `touch openspec/changes/catalog-lifecycle-approval/APPROVED` then `./ralph.sh --change catalog-lifecycle-approval 25` (preflight auto-commits the change folder incl. APPROVED → no exit-5; loop exits 0 when all 17 tasks checked; resumable).

## Blockers & Decisions Needed
- **None blocking.** Three flagged judgment calls (design D3/D5/D6) for accept-or-veto during/after ralph: (1) **KYC conjunct enforced upstream** — gate is on producer-`active`; `parties-compliance` tightens `ActivateProducer` so "active" ⟹ "KYC-verified"; no KYC bit in Module 0's contract. (2) **Producer-state projection** documented in design.md D3, not a standalone ADR (first read model — promotable on request). (3) **Separation-of-duties is audit-derived** (no governance columns on the 7 spine tables).
- Deferred follow-ons (named): `catalog-operator-console` (Filament approval-queue UI), `parties-compliance` (KYC model), Phase-3 referencers (cross-module retirement-blocking refs + BR-Identity-4/SKU-3/4).

## Open Patterns
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in arch plugin).
- **Consumer/delivery tests use `DatabaseMigrations`, NOT `RefreshDatabase`** — the recorder's `DB::afterCommit` inline hook + the executor's commit fire only at `transactionLevel 0`, which RefreshDatabase's wrapper transaction suppresses.
- **PG17 gate** (DB tasks / close): `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; bounded `docker exec pg pg_isready -q` loop (in-container `docker exec pg sleep 1`, NO host sleep); env prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 …`; `docker rm -f pg`. Traps in `knowledge/testing/rules.md`.
- **GUIDE §2.7 close** = verify both engines → merge `--no-ff` → semantic-verify (parallel audit agents) → `openspec archive --yes` + commit → push (human-gated). log.md via `memlog.sh`; hot.md ≤550 words; **APPROVED = human-only; never `git push` without explicit human OK.**
