---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-07
---

# Hot Cache

## Last Updated
**2026-07-07 — `parties-module-k-br-guards` ralph loop RUNNING. Task 2.2 (auto_renew + auto_renew_default columns) DONE, committed — 5/23.** One additive migration `2026_07_07_000002_…` adds `parties_clubs.auto_renew_default` (bool NOT-NULL, default `true`) + `parties_profiles.auto_renew` (bool NOT-NULL, default `true`) + `@property bool` + `boolean` casts on both models (Profile-5 K-side SCHEMA only; the CreateProfile inheritance behaviour is task 4.2). New `AutoRenewColumnsTest` (mirrors `ProfileLifecycleColumnsTest`). Verified up/down on BOTH engines.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Task 2.2 full loop **green**: SQLite full suite **1984/1984** (baseline 1980 + 4 new) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid. Focused test also **4/4 on PG17**.
- **Migration up/down proven on BOTH engines** (not deferred to 7.1): SQLite temp-DB `migrate`→`rollback`→`migrate` all exit 0; PG17 recipe `migrate:fresh`→`rollback`→`migrate` all exit 0 (`000002` DONE on both ups). `RefreshDatabase` only runs `up()` — verify `down()` via CLI.
- **PG17 recipe:** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (`pg` container UP on :55432; NOT :5432). Full suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB).

## Active Change & Next Task
- **`parties-module-k-br-guards` — 5/23 done. NEXT = task 2.3:** DROP `parties_clubs.invite_only` + remove it from `Club` model (@property/cast), `CreateClub`, and the `ClubCreated` event payload. _Acceptance:_ column gone; `ClubCreated::payload()` carries no `invite_only`; `grep -rn invite_only app/ database/` empty (lang/tests handled in 4.3/6.4).
  - **2.3 is a REMOVAL-with-ripple (the mirror of 2.2's additive column):** `ClubCreated::payload()` carries `'invite_only' => $club->invite_only` + `ClubTest.php:108` asserts `$event->payload['invite_only']` + factory L46 + DemoSeeder L273 + model @property/cast. **Grep `invite_only` across `app/ database/ tests/` first.** DROP via a NEW additive migration (`2026_07_07_000003_…`), never edit the `2026_06_15_000003` create-table.
- **Scope after 2.3:** 2.4 five localized exceptions → §3 ProducerAgreement (3.1 RM-22 guard ONLY — seeder already migrated in 2.1 / 3.2 Agreement-4 / **3.3 RM-20 inverts shipped tests L157+L206**) → §4 Profile+Club (**4.2 wires CreateProfile's auto_renew inheritance** — the default stays a harmless floor) → §5 Customer+Producer → §6 console+i18n → §7 close (human-gated).

## Blockers & Decisions Needed
- None. Change APPROVED; branch `ralph/parties-module-k-br-guards`. `origin/main` == local `main` @ `bfb8fc7`.
- **Append memory files via the Edit tool, not `cat >>` heredocs** (git-guardrails hook false-positives on spec-path strings in the body).

## Open Patterns
- **Additive NOT-NULL column → a DB `default` is MANDATORY (2.2).** SQLite `ADD COLUMN … NOT NULL` rejects a no-default column (DDL rule, any row count) AND un-wired writers (factory/seeder/Action) omit it → suite reds. Give it a default a later task simply overrides (the create-table "classifier carries no default" idiom does NOT transfer to `ALTER TABLE`). Explicit-field event payloads (list columns, not `toArray()`) → net-new columns don't ripple the wire format.
- **Grep-first before wiring any guard/removal (RM-08):** `->{col}` reads + `{col} =>` writes + `payload['{col}']` assertions + `callAction('{verb}')` console callers (invisible to the Action grep). Trust code-grep over the tracker.
- **Enum docblocks name related classes in backticks, never `{@see FQCN}`** — Pint's `fully_qualified_strict_types` auto-imports the FQCN into a `use` (cross-module = boundary violation).
