---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-07
---

# Hot Cache

## Last Updated
**2026-07-07 — `parties-module-k-br-guards` ralph loop RUNNING. Task 2.3 (drop `parties_clubs.invite_only`, Club-6 collapse) DONE, committed — 6/23.** An ATOMIC 14-file sweep: dropped the redundant `invite_only` bool (subsumed by `registration_flow_type=invitation_only`, MVP-DEC-022/design D6) from the `Club` model/`CreateClub` action/`ClubCreated` payload + 3 Filament surfaces + create-table migration/factory/`DemoSeeder` + 4 tests + `CONTEXT.md`. **DROP = edited the `2026_06_15_000003` create-table in place** (removed the column line), NOT a new drop-migration.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Task 2.3 full loop **green**: SQLite full suite **1984/1984** (net-neutral — removed assertions, no whole tests) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid.
- **Column-gone verified on BOTH engines** (temp-DB `migrate:fresh` + `Schema::hasColumn`/`getColumnListing`): `invite_only` absent on SQLite + PG17; all other cols (incl. `auto_renew_default`) intact; create-table migration runs clean.
- **PG17 recipe:** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (`pg` container UP on :55432; NOT :5432). Full suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB).

## Active Change & Next Task
- **`parties-module-k-br-guards` — 6/23 done. NEXT = task 2.4:** add five localized guard exceptions (EN+IT in `lang/{en,it}/parties.php`), each a `RuntimeException` subclass with static named factories mirroring the `SeparationOfDutiesViolation` shape: `ProducerAgreementScopeConflict`, `ProducerAgreementClubNotActive`, `ClubNotAcceptingMemberships`, `BelowMinimumRegistrationAge`, `ProducerReviewGovernedContentLocked`. _Acceptance:_ per-exception unit tests assert `toBeInstanceOf(RuntimeException::class)` (BARE, no `use` — global-namespace test trap) + the localized message resolves EN + IT.
- **Scope after 2.4:** §3 ProducerAgreement (3.1 RM-22 reject-guard / 3.2 Agreement-4 / 3.3 RM-20 inverts shipped tests L157+L206) → §4 Profile+Club (4.2 wires CreateProfile auto_renew inheritance; **4.3/6.4 invite_only sub-items PRE-SATISFIED by 2.3 EXCEPT lang EN/IT `fields.invite_only` + `ClubConsoleI18nTest` L48/L62 — those two remain for 6.4**) → §5 Customer+Producer → §6 console+i18n → §7 close (human-gated).

## Blockers & Decisions Needed
- None. Change APPROVED; branch `ralph/parties-module-k-br-guards`.
- **Append memory files via the Edit tool, not `cat >>` heredocs** (git-guardrails hook false-positives on spec-path strings in the body).

## Open Patterns
- **Column DROP (pre-launch) = EDIT the create-table migration IN PLACE, not a new drop-migration (2.3, inverse of 2.2's additive rule).** `grep database/ empty` is only satisfiable by removing the column line from create-table; safe because pre-launch (every run `migrate:fresh`). A hot.md "next task" prediction ≠ gospel — the task's OWN acceptance criteria override it (2.3's hint said "new migration, never edit create-table"; that was the 2.2 additive heuristic mis-applied to a DROP).
- **A column DROP is ATOMIC — sweep every reader/writer in ONE commit or the suite reds** (`Model::factory()` writing a dropped column → SQL "no such column" across the whole suite; page→Action named-arg → TypeError). Only a still-authored lang key + a usage-blind i18n test defer cleanly.
- **i18n subset-run trap:** the shared `scanOperatorConsoleHardcodedSinks` helper lives in `ProductMasterConsoleI18nTest` — a Console i18n test run WITHOUT it fails on `function_exists`. Not a regression; confirm against the FULL suite.
