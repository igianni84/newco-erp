---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-07
---

# Hot Cache

## Last Updated
**2026-07-07 — `parties-module-k-br-guards` ralph loop RUNNING. Task 2.1 (SettlementCadence enum + cast + PG CHECK) DONE, committed — 4/23. FIRST PHP task; full Quality Loop applied.** New `App\Modules\Parties\Enums\SettlementCadence` (3 cases `quarterly`(default)/`monthly`/`semi_annual` + `default()` → Quarterly) + cast on `ProducerAgreement.settlement_cadence` + additive migration `2026_07_07_000001_…` adding the PG-only nullable value-set CHECK (`col IS NULL OR col IN (…)`, from `cases()`). Ripple handled: event payload → `?->value`; DemoSeeder `annual → semi_annual` (mandatory — cast `ValueError`s on `annual`; pre-satisfies 3.1's seeder sub-item); factory → `SettlementCadence::Monthly`; 2 model-read assertions → enum. Console UNCHANGED (Filament renders the enum fine). New `SettlementCadenceEnumsTest` + `ProducerAgreementSchemaTest`.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Task 2.1 full loop **green on SQLite**: full suite **1980/1980** (baseline 1975 + 5 new cases) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid.
- **PG17 recipe:** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (`pg` container; NOT :5432). Full suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB). PG CHECK reject-lane (`ProducerAgreementSchemaTest`) verifies in 7.1's PG17 gate — mirrors the `HoldSchemaTest` pattern the baseline runs green on PG17.

## Active Change & Next Task
- **`parties-module-k-br-guards` — 4/23 done. NEXT = task 2.2:** migration adds `parties_profiles.auto_renew` (bool, not-null) + `parties_clubs.auto_renew_default` (bool, not-null, default `true`) + `@property`/casts. Acceptance: up/down clean on SQLite + PG17; model casts assert bool.
  - **`auto_renew` is not-null with NO default** → before adding it, grep every `parties_profiles` insert (factory + DemoSeeder + `CreateProfile` action) — each must supply `auto_renew` or the migration needs a backfill/default (mirror `2026_06_19_000001_add_lifecycle_columns_to_parties_profiles`). NO cast-ripple (net-new columns, no existing readers).
- **Two NEW Codebase Patterns in the change `progress.md` (read first):** (1) value-set enum cast = read-type change w/ grep-wide blast radius — model reads / event payload (`->value`) / out-of-set seeder+factory literals (`ValueError` at cast layer) / DB `->where` stays raw-string; Filament renders BackedEnum gracefully. (2) nullable PG value-set CHECK idiom + `HoldSchemaTest` both-lanes test.
- **Scope after 2.2:** 2.3 drop `invite_only` · 2.4 five localized exceptions → §3 ProducerAgreement (3.1 RM-22 guard ONLY — seeder already migrated / 3.2 Agreement-4 / **3.3 RM-20 inverts shipped tests L157+L206**) → §4 Profile+Club → §5 Customer+Producer → §6 console+i18n → §7 close (human-gated).

## Blockers & Decisions Needed
- None. Change APPROVED; branch `ralph/parties-module-k-br-guards`. `origin/main` == local `main` @ `bfb8fc7`.
- **Append memory files via the Edit tool, not `cat >>` heredocs** (git-guardrails hook false-positives on spec-path strings in the body).

## Open Patterns
- **Enum docblocks name related classes in backticks, never `{@see FQCN}`** — Pint's `fully_qualified_strict_types` auto-imports the FQCN into a `use` (design R4 trap; same-module = harmless but circular/inelegant, cross-module = boundary violation). The `HoldType` convention is backticked prose.
- **Grep-first before wiring any guard (RM-08):** `->{col}` reads + `{col} =>` writes + `callAction('{verb}')` console callers (invisible to the Action grep). Trust code-grep over the tracker.
