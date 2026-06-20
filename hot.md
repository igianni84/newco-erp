---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-20
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-20 (ralph §1 of `operator-console-parties-producer` — Producer read-only console landed).** The FIRST Parties (Module K) operator console is open. Tasks 1.1/1.2/1.3 done: `ProducerResource` (read-only, extends the kit's `OperatorConsoleResource`) + `ListProducers` (header create-link) + the `operator_console.producer.*` EN/IT block. It is the non-catalog **trait-reuse** pattern (ADR `2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse`): the Resource reuses the label/`version` helpers but supplies its **own** `status` + nullable `kyc_status` badge columns (NOT `lifecycleStateColumn()`). Because `getPages()` couples all three pages at panel-boot, §1 also scaffolded `CreateProducer` (real `createViaAction`) + a bare read-only `ViewProducer` — their form/lifecycle/KYC surfaces land in §2/§3/§4.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Branch `ralph/operator-console-parties-producer` GREEN: full suite 1143/1143 SQLite (6470 assn), phpstan 0, pint clean, `openspec validate --strict` OK, composer diff vs main empty.** (+5 tests vs the 1138 `main` baseline.) PG17 not yet re-run this change — the PG17 full gate is the §6 closing-chain task (6.1), per the catalog precedent.
- **Run-cmd gotcha:** full suite OOMs under bare `php artisan test` (128M). Use `php -d memory_limit=-1 vendor/bin/pest` and `php -d memory_limit=-1 vendor/bin/phpstan analyse`. PG17: docker `postgres:17`, prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **Active: `operator-console-parties-producer`** (APPROVED). 3 of 11 tasks done (§1 complete).
- **Next: §2 — task 2.1/2.2 (Producer create surface).** Add `ProducerResource::form()` (name/region/country required `TextInput`; appellation/website optional; description optional `Textarea`) + create-form field labels to `lang/{en,it}/operator_console.producer.fields.*` + `ProducerCreateConsoleTest` (valid submit → `draft` + 1 PII-free `ProducerCreated`/`newco_ops`; form exposes no status/kyc field; two same-name Producers both succeed). `CreateProducer` already carries the real `createViaAction` — §2 wires the form + lang + test.
- Then §3 (activate/retire on `ViewProducer` via `SurfacesDomainActions`), §4 (4 KYC verbs), §5 (kit-key i18n completeness), §6 (PG17 closing-chain). Read the change `design.md` (D1–D8) + `progress.md` Codebase Patterns each iteration.

## Blockers & Decisions Needed
- None. Reminder: ralph commits locally; **humans push**. No open ADR gate is crossed by this change (operator auth shipped, Filament pinned, read/write boundary decided; no queued consumer, no document storage, no SPA).

## Open Patterns
- **`getPages()` boots all pages eagerly** → a read-surface section must scaffold every page class in `getPages()` (List+Create+View) or the panel reds every Filament test. Documented in the change's Codebase Patterns.
- **Non-catalog kit reuse is at the TRAIT level** (`SurfacesDomainActions` + `OperatorConsoleCreateRecord` + `OperatorConsoleResource` label/version), NOT the catalog `OperatorConsoleViewRecord`/`lifecycleStateColumn()`. The Producer console is the template the rest of Parties (Club/Agreement/Customer) reuse. Operated-Clubs read uses `pluck('display_name')->implode()` to avoid importing `Club` (keeps the surface at `{Producer}`).
