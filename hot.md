---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-21
---

# Hot Cache

## Last Updated
**2026-06-21 (`operator-console-parties-supply-side` — task 10.1 of 19 DONE → ProducerAgreement i18n kit completeness, 18/19).** Added `ProducerAgreementConsoleI18nTest.php` — the FIVE-guard kit-key-completeness pattern (now four-times-proven: Producer+Club+Spine+ProducerAgreement), mirroring `ClubConsoleI18nTest`. Two dataset fns `producerAgreementConsoleKitKeys()` (**20** keys) + `producerAgreementConsoleItDiffersKeys()` (**18** = kit − {label,plural_label}). Kit = label/plural_label + columns.{producer,club,status,term_start,term_end,version} + producer_wide + fields.{producer,club,term_start,term_end,settlement_cadence} + actions.{create,activate,terminate} + notifications.{activated,terminated,action_failed}. Guards: (1) EN baseline; (2) IT-differs; (3) label/plural_label EN-fallback (DEC-127); (4) IT⊆EN filtered `str_starts_with($dotKey,'producer_agreement.')` (trailing dot load-bearing — excludes sibling `producer.*` + future `producer_agreements.*`); (5) reused `scanOperatorConsoleHardcodedSinks` scoped `ProducerAgreementResource`. **Test-only — every key already authored across groups 7–9.** Non-vacuity proven (drop EN `notifications.terminated` → guards 1+4 fire; restored, lang diff empty).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (level max) · Pint 1.29.1. SQLite dev; prod PG17.
- **GREEN after group 10:** full SQLite suite **1324/1324** (7308 assn, +42); `ProducerAgreementConsoleI18nTest` 42/42 (85 assn = 20 EN + 18 IT + 2 fallback + 1 IT⊆EN + 1 sink); phpstan 0; pint + pint --test clean; `openspec validate … --strict` valid; composer diff vs `main` empty. (Group 10 is locale + static source scan, no DB — PG17 is scoped to groups 6+11 only.)
- **Run-cmd gotchas:** full suite OOMs under bare `php artisan test` → use `php -d memory_limit=-1 vendor/bin/pest` / `… phpstan analyse`. **i18n tests reuse the helper `scanOperatorConsoleHardcodedSinks` (in `…/Catalog/ProductMasterConsoleI18nTest.php`) → run via `--filter`/full suite, NOT a bare path; APPEND that Catalog file for a folder-wide PG17 run.** PG17: docker container `newco-pg17-test` (Up), prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco`.

## Active Change & Next Task
- **Active:** `operator-console-parties-supply-side` (18/19 done: 1.1, 2.1–2.3, 3.1–3.2, 4.1–4.2, 5.1, 6.1, 7.1–7.3, 8.1–8.2, 9.1–9.2, 10.1). Branch `ralph/operator-console-parties-supply-side`.
- **Next: group 11 (11.1) — the FINAL task.** `ProducerAgreementConsoleChainTest.php` — one `it()` driving the whole slice through the PAGES: seed Producer event-free → `CreateProducerAgreement` (A, draft, Producer-wide) → `callAction('activate')` on A → `CreateProducerAgreement` (B, same scope, draft) → `callAction('activate')` on B (A→superseded via inline OR-branch) → `callAction('terminate')` on B. Assert emergent set `toEqualCanonicalizing(['ProducerAgreementCreated','ProducerAgreementActivated','ProducerAgreementCreated','ProducerAgreementActivated','ProducerAgreementSuperseded','ProducerAgreementTerminated'])`; every event module===`parties`/role===NewcoOps/non-null actor_id; `ProducerAgreementSuperseded` carries B's `ProducerAgreementActivated` event id as `causation_id` (loose `toEqual`). Green on SQLite AND PG17 (preamble command + appended Catalog i18n file). Re-instantiate `Livewire::test(ViewProducerAgreement::class,['record'=>$id])` per `callAction` (proven group-6 idiom). **After green: re-verify all 19 acceptance bullets, then reply `<promise>CHANGE_COMPLETE</promise>` (do NOT archive/merge — humans do that).**

## Blockers & Decisions Needed
- **None.** Cleanup: `newco-pg17-test` docker container is running (`docker rm -f newco-pg17-test` when done with the change).

## Open Patterns
- **i18n five-guard kit completeness now FOUR-times-proven (Producer+Club+Spine+ProducerAgreement).** Test count is mechanical: |kit| + |differs| + 2 + 1 + 1. ProducerAgreement = 20+18+2+1+1 = 42 (Club was 44 — no infolist-only extra `fields.*` key here: `settlement_cadence` doubles as create field + infolist label; infolist producer/club/status/terms reuse `columns.*`). Subtleties: trailing dot in the `str_starts_with` filter (collision-safe vs sibling `producer.*`); enumerate literal `__()` keys too; run via `--filter` (sink helper lives in another file).
- **Supersession = side-effect not verb (D8).** Group 11 chain reuses the `ActivateProducerAgreement` OR-branch: activate B in A's NULL-safe `(producer_id,club_id)` scope → A superseded, `ProducerAgreementSuperseded` carries B's `ProducerAgreementActivated` id as `causation_id`. Fixture: ONE shared Producer, A+B both `club_id=null` (two bare factories spawn separate Producers — no shared scope).
- **Group 11 closing-chain** full recipe in progress.md Codebase Patterns + the 10.1 entry. Then change is COMPLETE.
