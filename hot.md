---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-21
---

# Hot Cache

## Last Updated
**2026-06-21 (`operator-console-parties-customer` — GROUP 4 GREEN: i18n kit-key completeness).** Added `CustomerConsoleI18nTest` (test-only, no app/lang change) — the capability-close i18n guard mirroring `ClubConsoleI18nTest`. Enumerates the **26** kit-contract keys (`label`/`plural_label` + `columns.{name,email,status,kyc_status,sanctions_status,account_status,profiles,version}` + `fields.{email,name,phone,date_of_birth,preferred_currency,preferred_locale}` + `actions.{create,activate,suspend,reactivate,close}` + `notifications.{activated,suspended,reactivated,closed,action_failed}`) with FIVE guards: EN-baseline ×26, IT-differs ×24 (kit-minus-`{label,plural_label}`, `it`-authored AND ≠ en), `label`/`plural_label` EN-fallback, IT⊆EN filtered `str_starts_with($dotKey,'customer.')`, and the reused `scanOperatorConsoleHardcodedSinks()` scoped to `CustomerResource*`. **Proved non-vacuous** by a reversible mutation (dropped EN `notifications.closed` → RED on EN-baseline + IT⊆EN, restored, re-green). Task 4.1 done (**8 of 9**).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: SQLite 1396/1396 (7629 assn, +54 tests/+109 assn), filtered `CustomerConsoleI18nTest` 54/54, phpstan 0, pint clean, `ModuleBoundariesTest` 3/3 (189 assn) UNCHANGED, validate --strict valid, composer diff vs main empty.** (No PG17 run this group — PG is the group-5 chain.)
- **main is 2 commits ahead of origin/main, UNPUSHED** (supply-side merge+archive; push classifier-denied earlier) — still deferred to human.
- Run-cmd: full `php -d memory_limit=-1 vendor/bin/pest`; filter `--filter=CustomerConsoleI18nTest`. **i18n tests run via `--filter` or full suite, NEVER a bare file path** (the shared `scanOperatorConsoleHardcodedSinks` helper lives in `ProductMasterConsoleI18nTest` → helper-load false-red). PG17 chain command in tasks.md preamble (group 5).

## Active Change & Next Task
- **Active: `operator-console-parties-customer` (8 of 9 tasks done).** Loop continues.
- **Next = Group 5 (task 5.1): `CustomerConsoleChainTest` (PG17, D9) — the LAST task.** One `it()` driving the Create→View **pages**: **(a)** `Livewire::test(CreateCustomer)->fillForm([valid])->call('create')` → `pending` Customer; then `Livewire::test(ViewCustomer,['record'=>$id])->callAction('activate')->assertNotified(action_failed)` → stays `pending`, **records no event** (gate-unmet). **(b)** factory-seed a **gate-met, profile-less** Customer (`email_verified_at`/`tc_accepted_at`/`privacy_accepted_at`/`sanctions_status=Passed`, kyc_required null → event/Account/Profile-free) and drive activate→suspend→reactivate→close; assert emergent `DomainEvent::pluck('name')->all()` `toEqualCanonicalizing(['CustomerCreated','CustomerActivated','CustomerSuspended','CustomerReactivated','CustomerClosed'])`, every event `module==='parties'` / `actor_role===NewcoOps` / `actor_id` non-null, a representative `actor_id` loose-`toEqual` operator. **Run SQLite AND PG17** (preamble cmd; append `ProductMasterConsoleI18nTest.php` to the folder run so the sink helper loads). On green → final pass → `<promise>CHANGE_COMPLETE</promise>`.

## Blockers & Decisions Needed
- **Push decision (human, carried over):** main holds supply-side merge+archive locally; origin/main not updated.
- Otherwise none.

## Open Patterns
- **Console i18n completeness test = enumerate kit contract + 5 guards (proven 5×).** Full recipe + the two gotchas (enumeration is the ONLY guard for string-concatenated keys; `--filter`-not-bare-path for the helper) in the change's `progress.md` §Codebase Patterns.
- **All Customer i18n was authored collision-free in groups 1–3** (`columns.email`/`fields.email` IT='Indirizzo email'≠EN 'Email') → group 4 added ZERO keys, pure verification.
- **Non-catalog status-FSM view page = `ViewRecord` + `use SurfacesDomainActions` + bespoke `getHeaderActions()`** (D8 CLOSED). Cross-slice gate (D5) is a test-seed concern, not code. Full patterns in `progress.md`.
