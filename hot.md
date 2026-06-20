---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-20 (ralph §6 of `operator-console-parties-producer` — CHANGE COMPLETE, all 11 tasks done).** Task 6.1 done: added `ProducerConsoleChainTest` (1 test / 53 assn) — the change's closing integration proof. One page-driven `it()` drives a Producer through the WHOLE console slice as an operator demo: `CreateProducer->create` → `ViewProducer` `requireKyc`→`verifyKyc`→`activate` → seed two `active` Clubs (`Club::factory()`, event-free) → `retire`. It asserts the EMERGENT event set `DomainEvent::query()->pluck('name')->all()` `toEqualCanonicalizing(['ProducerCreated','ProducerActivated','ProducerRetired','ClubSunset','ClubSunset'])` (the two KYC steps are event-silent, § 4.4), a `foreach` over all 5 asserting the envelope (`module==='parties'`, `actor_role` NewcoOps, non-null `actor_id`), a representative `actor_id`==operator on BOTH surfaces (loose `toEqual`), and both `ClubSunset` carrying the `ProducerRetired` id as `causation_id`. The exact-5 count was VERIFIED pre-write by reading consumers: Catalog's `ProducerLifecycleProjector` consumes the lifecycle events into the `ProducerState` READ MODEL only (never the recorder → no extra `domain_events` row), and `ClubSunset` has no consumer.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Branch `ralph/operator-console-parties-producer` GREEN: full suite 1206/1206 SQLite (6850 assn), phpstan 0, pint clean, `openspec validate --strict` OK, composer diff vs main empty.** (+68 vs the 1138 `main` baseline; +1 this iteration.) **PG17 gate ✓ (§6):** `--filter=ProducerConsoleChainTest` 1/1 (53 assn) AND the whole Parties folder + the Catalog scanner file 83/83 (450 assn) on docker `postgres:17`.
- **Run-cmd gotchas:** full suite OOMs under bare `php artisan test` (128M) → use `php -d memory_limit=-1 vendor/bin/pest` / `… phpstan analyse`. A test reusing a sibling file's top-level helper (e.g. `scanOperatorConsoleHardcodedSinks`) must be run via `--filter`/full suite, NOT a file OR directory path (a Parties-only path omits the Catalog declaring file → `function_exists` guard false-reds; for a folder-wide PG17 run APPEND `tests/Feature/Modules/OperatorPanel/Catalog/ProductMasterConsoleI18nTest.php`). PG17: docker `postgres:17` container `newco-pg17-test`, prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **`operator-console-parties-producer` is COMPLETE — all 11 tasks `[x]`, full quality loop + PG17 gate green, validate --strict OK.** Replied `<promise>CHANGE_COMPLETE</promise>`. **Humans review/merge/archive** (`openspec archive operator-console-parties-producer --yes`) — ralph does NOT push or archive.
- **Next change (after archive):** `operator-console-parties-supply-side` (Club + ProducerAgreement consoles) — reuses the proven non-catalog recipe set (ADR 2026-06-20) consolidated in this change's progress Codebase Patterns. Run `/spec-to-change` or pick it up if already APPROVED.

## Blockers & Decisions Needed
- None. Reminder: ralph commits locally; **humans push**. No open ADR gate crossed by this change. Cleanup: the `newco-pg17-test` docker container is left running for reuse (`docker rm -f newco-pg17-test` to drop it).

## Open Patterns
- **The non-catalog Parties console pattern is now PROVEN end-to-end (create + dual-FSM lifecycle + retire cascade, SQLite+PG17).** The whole recipe set — Resource (own status/kyc columns, no `lifecycleStateColumn`), View page (`SurfacesDomainActions` trait, form-less verbs, two FSMs on one page), create write-through, i18n kit-key completeness guard, and the closing-chain test (page-driven `it()`, emergent-set `toEqualCanonicalizing`, set-wide envelope `foreach`, loose `toEqual` for uncast bigints, `+ Catalog scanner file` PG17 invocation) — is consolidated in the change's progress Codebase Patterns. Club/Agreement/Customer consoles reuse it.
