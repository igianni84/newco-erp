---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-21
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-21 (`operator-console-parties-supply-side` — group 3 / tasks 3.1·3.2 of 19 DONE).** Ralph loop on `ralph/operator-console-parties-supply-side`. This change builds the **Club** + **ProducerAgreement** operator consoles over shipped Parties Actions — pure operator surface, no domain code. Group 3 shipped the **Club create surface**: `ClubResource::form()` (display_name TextInput; `producer_id` model-driven Select off `Producer::query()`; `registration_flow_type` enum-driven Select; optional fee `amount`->numeric + `currency` Select; `generates_credit`/`invite_only` Toggles; **NO `status`**) + 3 private option helpers; EN+IT `club.fields.{display_name,producer,registration_flow_type,amount,currency}` (all IT≠EN); and `ClubCreateConsoleTest` (4 it()). Fixed the latent `CreateClub` `amount` guard (`is_string||is_int` → `is_numeric`) — a `->numeric()` field dehydrates as a **float**.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **GREEN after group 3:** full SQLite suite **1215/1215** (6922 assn, +4 tests), `ClubCreateConsoleTest|ClubResourceTest` 9/9 (63 assn), `ModuleBoundariesTest` 3/3 (189 assn) — admits `ClubRegistrationFlowType` in both `CreateClub` + `ClubResource`, **proving group 1**; phpstan 0, pint clean, `openspec validate operator-console-parties-supply-side --strict` valid, composer diff vs `main` empty.
- **Run-cmd gotchas:** full suite OOMs under bare `php artisan test` (128M) → use `php -d memory_limit=-1 vendor/bin/pest` / `… phpstan analyse`. i18n tests reuse a top-level helper (`scanOperatorConsoleHardcodedSinks`, in `…/Catalog/ProductMasterConsoleI18nTest.php`) → run via `--filter`/full suite, NOT a bare file/dir path (false-red); for a folder-wide PG17 run APPEND that Catalog file. PG17: docker `postgres:17` container `newco-pg17-test`, prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **Active:** `operator-console-parties-supply-side` (6/19 done: 1.1, 2.1, 2.2, 2.3, 3.1, 3.2). Branch `ralph/operator-console-parties-supply-side`.
- **Next: group 4 (4.1/4.2).** Fill `ViewClub` — `extends ViewRecord` + `use SurfacesDomainActions` (NOT `OperatorConsoleViewRecord`, D1), `i18nKey()='club'`, two **form-less** verbs via `lifecycleAction` (no `confirmationKey`, D3): `sunset`→`sunset`→`app(SunsetClub::class)->handle($this->recordOf(Club::class,$r)->id)`; `close`→`closed`→`app(CloseClub::class)->handle(...->id)` (D4). **No `activate`** (D9); close-from-`active` rejected (`IllegalClubTransition`). + `actions.{sunset,close}` & `notifications.{sunset,closed,action_failed}` i18n + `ClubLifecycleConsoleTest`. Group order: 2 Club-read ✓ → 3 Club-create ✓ → 4 Club-lifecycle → 5 Club-i18n → 6 Club-PG17 → 7–11 ProducerAgreement. Recipe = predecessor `archive/2026-06-20-operator-console-parties-producer/progress.md` Codebase Patterns + design D1–D12.

## Blockers & Decisions Needed
- **None.** Cleanup: `newco-pg17-test` docker container may be running (`docker rm -f newco-pg17-test`).

## Open Patterns
- **Create form = `Resource::form()` (D6), `fields.*` for every input.** Model-driven picker = `Model::query()->get()->mapWithKeys()->all()` (FK-id key, a read); enum-driven Select = `collect(OperandEnum::cases())->mapWithKeys(fn → [value=>value])` (token label, no per-value i18n key, operand-enum import = {Enums} carve-out). **`->numeric()` dehydrates a FLOAT → narrow with `is_numeric()`, never `is_string`/`is_int`** (lessons.md 2026-06-21; catalog `CreateProductVariant` is the reference). Money `(int)$amount` only when amount+currency both present, else null (D11).
- **Verify model attrs, not task prose:** `Producer` has `name` (not `display_name`); task 7.1 repeats `producer->display_name` → use `producer->name` (but `club?->display_name` IS correct). lessons.md 2026-06-21.
- **Operand vs state enum split (D2/D7):** console imports/constructs **operand** enums (`ClubRegistrationFlowType`); **state** enums (`ClubStatus`/`ProducerAgreementStatus`) render via the cast, never imported. No `{@see Money}` docblock unless Money is imported+used in that file (Pint adds a dangling import).
