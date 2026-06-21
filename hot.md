---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-21
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.

## Last Updated
**2026-06-21 (`operator-console-parties-supply-side` — group 5 / task 5.1 of 19 DONE).** Ralph loop on `ralph/operator-console-parties-supply-side`. This change builds the **Club** + **ProducerAgreement** operator consoles over shipped Parties Actions — pure operator surface, no domain code. Group 5 shipped **`ClubConsoleI18nTest.php`**: the capability-close i18n guard (FIVE guards mirroring `ProducerConsoleI18nTest`), driven off two top-level dataset fns `clubConsoleKitKeys()` (21 keys) + `clubConsoleItDiffersKeys()` (19 = kit minus `label`/`plural_label`). Enumerates EVERY key the console resolves — string-concat kit keys (label/plural_label/columns.version/actions.{sunset,close}/notifications.{sunset,closed,action_failed}) AND literal `__()` keys (columns.{display_name,producer,registration_flow_type,status}, all 8 `fields.*` incl. infolist-only `fee`, actions.create). Test-only — no source/lang change (every key already authored across groups 2–4).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **GREEN after group 5:** full SQLite suite **1264/1264** (7063 assn, +44 tests), `ClubConsoleI18nTest` 44/44 (89 assn = 21 EN + 19 IT + 2 fallback + 1 IT⊆EN + 1 sink); phpstan 0; pint + pint --test clean; `openspec validate operator-console-parties-supply-side --strict` valid; composer diff vs `main` empty. Non-vacuity proven (dropped `club.fields.fee` from `lang/en` → guards 1+4 fired → restored).
- **Run-cmd gotchas:** full suite OOMs under bare `php artisan test` (128M) → use `php -d memory_limit=-1 vendor/bin/pest` / `… phpstan analyse`. **i18n tests reuse the top-level helper `scanOperatorConsoleHardcodedSinks` (in `…/Catalog/ProductMasterConsoleI18nTest.php`) → run via `--filter`/full suite, NOT a bare file/dir path (false-red).** For a folder-wide PG17 run APPEND that Catalog file. PG17: docker `postgres:17` container `newco-pg17-test`, prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **Active:** `operator-console-parties-supply-side` (9/19 done: 1.1, 2.1–2.3, 3.1–3.2, 4.1–4.2, 5.1). Branch `ralph/operator-console-parties-supply-side`.
- **Next: group 6 (6.1).** Add `ClubConsoleChainTest.php` — one `it()` driving a Club through the console **pages**: `Livewire::test(CreateClub::class)->fillForm([...])->call('create')` (operating Producer seeded event-free via `Producer::factory()`), then `Livewire::test(ViewClub::class,['record'=>$id])->callAction('sunset')` → `callAction('close')`. Assert emergent event set `DomainEvent::query()->pluck('name')->all()` `->toEqualCanonicalizing(['ClubCreated','ClubSunset','ClubClosed'])`; `foreach` every event `module==='parties'`, `actor_role===ActorRole::NewcoOps`, `actor_id` non-null; a representative `actor_id` `toEqual` the operator (loose — PG numeric string). **Must be green SQLite AND PG17** (preamble cmd; append the Catalog i18n file for folder-wide). Group order: 6 Club-PG17 chain → 7–11 ProducerAgreement (Resource → create → lifecycle activate/terminate → i18n → PG17 chain). Recipe = predecessor `archive/2026-06-20-operator-console-parties-producer/progress.md` Codebase Patterns + this change's progress.md Codebase Patterns + design D1–D12.

## Blockers & Decisions Needed
- **None.** Cleanup: `newco-pg17-test` docker container may be running (`docker rm -f newco-pg17-test`).

## Open Patterns
- **Console i18n kit-key completeness test (twice-proven: Producer + Club; group 10 repeats for ProducerAgreement).** Five guards (EN baseline · IT-differs · label/plural_label EN-fallback · IT⊆EN filtered `str_starts_with($dotKey,'<entity>.')` — trailing dot load-bearing · sink scan scoped `str_contains($pathname,'<Entity>Resource')` behind `function_exists`). Enumerate literal `__()` keys too, incl. infolist-only `fields.fee`. Run via `--filter`. Test count = |kit|+|differs|+2+1+1.
- **Lifecycle View page (twice-proven: Producer + Club).** `extends ViewRecord` + `use SurfacesDomainActions`, own `i18nKey()`, `getHeaderActions()` = `$this->lifecycleAction(verb, successKey, fn (Model $r, string $notes) => app(<Action>::class)->handle($this->recordOf(<Model>::class,$r)->id))`. **verb→successKey is NOT identity:** `close`→`closed`; label key = `Str::snake(verb)`. Form-less verb = no `form`/`confirmationKey`. Out-of-state throws a `RuntimeException` subtype → base catch → `action_failed` danger; exceptions stay PROSE in docblocks (Pint would re-add the forbidden `Parties\Exceptions` import). `{@see OperatorConsoleViewRecord}` is intra-module → importable, Pint-clean.
- **Operand vs state enum split (D2/D7):** console imports/constructs **operand** enums (`ClubRegistrationFlowType` in CreateClub); **state** enums (`ClubStatus`/`ProducerAgreementStatus`) render via the cast, never imported. Lifecycle View pages import only the **Actions** + the **Model**.
- **Lifecycle/create tests use `DatabaseMigrations`** (real commit; factories bypass Actions → only console events recorded); read-only Resource + i18n tests use `RefreshDatabase`/no-DB. PG17 uncast bigints (`actor_id`) → assert loose `toEqual`, never `toBe`.
