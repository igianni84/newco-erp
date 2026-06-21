---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-21
---

# Hot Cache

## Last Updated
**2026-06-21 (`operator-console-parties-customer` — GROUP 1 GREEN: Customer read-only console).** First **demand-side** Parties console shipped read-side: `CustomerResource` (read-only) + `ListCustomers` (create-LINK) + **real** `CreateCustomer` (createViaAction done) + bare `ViewCustomer` stub + EN/IT resource/infolist i18n. Three orthogonal lifecycle badges (`status`/`kyc_status`/`sanctions_status`) via a shared `enumBadgeState()` cast resolver (no `Parties\Enums` import) + co-provisioned Account status + Profiles. Tasks 1.1/1.2/1.3 done (3 of 9).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: SQLite 1330/1330 (7386 assn, +5), phpstan 0, pint clean, `ModuleBoundariesTest` 3/3, validate --strict valid, composer diff vs main empty.** (No PG17 run this group — scoped to group 5 chain.)
- **main is 2 commits ahead of origin/main, UNPUSHED** (supply-side merge+archive; push classifier-denied earlier) — still deferred to human.
- Run-cmd: full suite `php -d memory_limit=-1 vendor/bin/pest`; filter `--filter=CustomerResourceTest`. PG17 chain command in tasks.md preamble (group 5).

## Active Change & Next Task
- **Active: `operator-console-parties-customer` (3 of 9 tasks done).** Loop continues.
- **Next = Group 2 (task 2.1/2.2): Customer create surface.** Remaining work is `CustomerResource::form()` ONLY (email/name required TextInputs; preferred_currency/preferred_locale required Selects off `Currency`/`SupportedLocale` PLATFORM enum cases; phone TextInput; date_of_birth DatePicker; **NO `status`**) + `fields.{email,name}` i18n + `CustomerCreateConsoleTest`. **The real `createViaAction` + operands + `createRejectionField` already shipped group 1** (dormant until the form exists). Then 3 lifecycle (activate/suspend/reactivate/close on ViewCustomer) · 4 i18n completeness · 5 PG17 chain.

## Blockers & Decisions Needed
- **Push decision (human, carried over):** main holds supply-side merge+archive locally; origin/main not updated.
- **LANDMINE (design D5, group 3):** `activate`'s gate is cross-slice (onboarding timestamps + sanctions=passed + KYC) → the verb idles/rejects in prod until consumer-onboarding + compliance ship. Correct behaviour — do NOT set gate columns from the console. Chain test (group 5) seeds a gate-met, profile-less Customer (D9).
- Otherwise none.

## Open Patterns
- **Read-surface group = ONE green unit (4th proof).** `getPages()` eager-references all pages → Resource + List + **real** Create + bare View ship together. `OperatorConsoleCreateRecord`'s two abstract methods → the clean Create scaffold IS the real page (group-2 work = `form()` only). Full Codebase Patterns in the change's `progress.md`.
- **Three-badge shared resolver** `enumBadgeState(attr): Closure` (table + infolist) + nullable-`hasOne` `accountStatusState()` (`->status->value`, no instanceof) — both in progress.md Codebase Patterns. Operands platform-level (D6) → `ModuleBoundariesTest` untouched.
- **i18n incremental + IT 'Indirizzo email'** (the EN/IT 'Email' collision; group-4 IT-differs guard needs IT != EN).
- **Rule-of-three RESOLVED (ADR 2026-06-21):** non-catalog consoles stay at the trait level; `OperatorConsoleViewRecord` stays catalog-only. ViewCustomer (group 3) = `ViewRecord` + `use SurfacesDomainActions` + bespoke `getHeaderActions()`.
