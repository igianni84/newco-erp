---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-21
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-21 (`operator-console-parties-supply-side` — group 4 / tasks 4.1·4.2 of 19 DONE).** Ralph loop on `ralph/operator-console-parties-supply-side`. This change builds the **Club** + **ProducerAgreement** operator consoles over shipped Parties Actions — pure operator surface, no domain code. Group 4 shipped the **Club status lifecycle**: filled the bare `ViewClub` stub → `extends ViewRecord` + `use SurfacesDomainActions` (NOT `OperatorConsoleViewRecord`, D1), `i18nKey()='club'`, `getHeaderActions()` = two **form-less** verbs via `lifecycleAction` (no `confirmationKey`, D3): `sunset`→`sunset`→`app(SunsetClub::class)->handle($this->recordOf(Club::class,$r)->id)`; `close`→`closed`→`app(CloseClub::class)->handle(…->id)` (D4). **No `activate`** (D9). + EN/IT `club.actions.{sunset,close}` + new `club.notifications.{sunset,closed,action_failed}` block (IT≠EN) + `ClubLifecycleConsoleTest` (5 it(), both OR-branches: close-from-`active` reject + out-of-state sunset).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **GREEN after group 4:** full SQLite suite **1220/1220** (6974 assn, +5 tests), `ClubLifecycleConsoleTest` 5/5 (52 assn); phpstan 0 (`NoEloquentWriteInOperatorPanelRule` over new `ViewClub` clean — writes route through Actions), pint clean, `openspec validate operator-console-parties-supply-side --strict` valid, composer diff vs `main` empty.
- **Run-cmd gotchas:** full suite OOMs under bare `php artisan test` (128M) → use `php -d memory_limit=-1 vendor/bin/pest` / `… phpstan analyse`. i18n tests reuse a top-level helper (`scanOperatorConsoleHardcodedSinks`, in `…/Catalog/ProductMasterConsoleI18nTest.php`) → run via `--filter`/full suite, NOT a bare file/dir path (false-red); for a folder-wide PG17 run APPEND that Catalog file. PG17: docker `postgres:17` container `newco-pg17-test`, prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **Active:** `operator-console-parties-supply-side` (8/19 done: 1.1, 2.1–2.3, 3.1–3.2, 4.1–4.2). Branch `ralph/operator-console-parties-supply-side`.
- **Next: group 5 (5.1).** Add `ClubConsoleI18nTest` — 5 guards mirroring `ProducerConsoleI18nTest`: EN baseline, IT-differs (kit-minus-`{label,plural_label}`), `label`/`plural_label` EN-fallback, IT⊆EN over the `club.*` dotted block (filter `str_starts_with($dotKey,'club.')`), and the reused `scanOperatorConsoleHardcodedSinks()` behind `function_exists`, scoped to `ClubResource*`. Kit keys: `label`/`plural_label`/`columns.{display_name,producer,registration_flow_type,status,version}`/`actions.{sunset,close,create}`/`notifications.{sunset,closed,action_failed}`/`fields.*` — all now exist. Run via `--filter=ClubConsoleI18nTest`, NOT a bare path. Group order: 5 Club-i18n → 6 Club-PG17 chain → 7–11 ProducerAgreement. Recipe = predecessor `archive/2026-06-20-operator-console-parties-producer/progress.md` Codebase Patterns + design D1–D12.

## Blockers & Decisions Needed
- **None.** Cleanup: `newco-pg17-test` docker container may be running (`docker rm -f newco-pg17-test`).

## Open Patterns
- **Lifecycle View page (twice-proven: Producer + Club).** `extends ViewRecord` + `use SurfacesDomainActions`, own `i18nKey()`, `getHeaderActions()` = `$this->lifecycleAction(verb, successKey, fn (Model $r, string $notes) => app(<Action>::class)->handle($this->recordOf(<Model>::class,$r)->id))`. **verb→successKey is NOT identity:** `close`→`closed` (success key = past-participle state); label key = `Str::snake(verb)`. Form-less verb = no `form`/`confirmationKey` args. Out-of-state throws a `RuntimeException` subtype (`IllegalClubTransition`) → base-type catch → `action_failed` danger; exceptions stay PROSE in docblocks (Pint would re-add the forbidden `Parties\Exceptions` import). `{@see OperatorConsoleViewRecord}` is intra-module → importable, Pint-clean (D5 note).
- **Operand vs state enum split (D2/D7):** console imports/constructs **operand** enums (`ClubRegistrationFlowType` in CreateClub); **state** enums (`ClubStatus`/`ProducerAgreementStatus`) render via the cast, never imported. Lifecycle View pages import only the **Actions** + the **Model** (no enum).
- **Lifecycle/create tests use `DatabaseMigrations`** (real commit; factories bypass Actions → record no event, so the only events are the console's); read-only Resource tests use `RefreshDatabase`. PG17 uncast bigints (`actor_id`) → assert loose `toEqual`, never `toBe`.
