---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-21
---

# Hot Cache

## Last Updated
**2026-06-21 (`operator-console-parties-customer` — GROUP 2 GREEN: Customer create surface).** The write-through create surface completed: `CustomerResource::form()` (email/name `TextInput`, preferred_currency/preferred_locale required `Select` off `currencyOptions()`/`localeOptions()`, phone `TextInput`, date_of_birth `DatePicker`; **NO `status`**) + `fields.{email,name}` EN/IT i18n + `CustomerCreateConsoleTest`. The real `createViaAction`/operands/`createRejectionField` already shipped group 1 — now exercised end-to-end. Tasks 2.1/2.2 done (5 of 9).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: SQLite 1333/1333 (7424 assn, +3 tests/+38 assn), phpstan 0, pint clean, `ModuleBoundariesTest` 3/3 (189 assn) UNCHANGED, validate --strict valid, composer diff vs main empty.** (No PG17 run this group — scoped to group 5 chain.)
- **main is 2 commits ahead of origin/main, UNPUSHED** (supply-side merge+archive; push classifier-denied earlier) — still deferred to human.
- Run-cmd: full suite `php -d memory_limit=-1 vendor/bin/pest`; filter `--filter=CustomerCreateConsoleTest`. PG17 chain command in tasks.md preamble (group 5).

## Active Change & Next Task
- **Active: `operator-console-parties-customer` (5 of 9 tasks done).** Loop continues.
- **Next = Group 3 (task 3.1/3.2): Customer status lifecycle verbs on ViewCustomer.** Fill `…/Pages/ViewCustomer.php` — `extends \Filament\Resources\Pages\ViewRecord` + `use SurfacesDomainActions` (NOT `OperatorConsoleViewRecord`, D1/D8); `i18nKey()='customer'`; `getHeaderActions()` = four form-less, no-`confirmationKey` verbs via `lifecycleAction` (activate/suspend/reactivate/close → `app(ActivateCustomer/SuspendCustomer/ReactivateCustomer/CloseCustomer::class)->handle($this->recordOf(Customer::class,$r)->id)`, each closure `(Model $record, string $notes)`, `$notes` unused). **No** hold/KYC/sanctions/account/profile verb. Then `actions.{activate,suspend,reactivate,close}` + `notifications.{activated,suspended,reactivated,closed,action_failed}` i18n + `CustomerLifecycleConsoleTest`. Then 4 i18n completeness · 5 PG17 chain.

## Blockers & Decisions Needed
- **Push decision (human, carried over):** main holds supply-side merge+archive locally; origin/main not updated.
- **LANDMINE (design D5, group 3):** `activate`'s gate is cross-slice (onboarding timestamps + sanctions=passed + KYC) → the verb idles/rejects in prod until consumer-onboarding + compliance ship. Correct behaviour — do NOT set gate columns from the console. The lifecycle/chain tests seed a gate-met, profile-less Customer (D9).
- Otherwise none.

## Open Patterns
- **Select-options helper = sibling idiom** `collect(Enum::cases())->mapWithKeys(fn($c)=>[$c->value=>$c->value])->all()` wired `->options(self::xOptions(...))`. Customer's drive PLATFORM `Currency`/`SupportedLocale` → imported freely, boundary untouched (D6 proven again). `->email()` is a real Filament `TextInput` method; duplicate-email rejection comes from the ACTION (valid format passes form, action's uniqueness pre-check → `email` field via `createRejectionField`).
- **Read-surface group = ONE green unit** (the `getPages()` boot coupling); **group-2 = `form()` only** (createViaAction shipped group 1). Three-badge shared `enumBadgeState()` resolver + nullable-`hasOne` `accountStatusState()`. Full Codebase Patterns in the change's `progress.md`.
- **i18n incremental** (group 3 adds the verb actions/notifications). **IT 'Indirizzo email'** breaks the EN/IT 'Email' collision (group-4 IT-differs guard needs IT != EN). Recompute the group-4 kit count: |kit| EN + |differs| IT + 2 fallback + 1 IT⊆EN + 1 sink.
- **Rule-of-three RESOLVED (ADR 2026-06-21, D8):** non-catalog consoles stay at the trait level; `OperatorConsoleViewRecord` stays catalog-only. ViewCustomer (group 3) = `ViewRecord` + `use SurfacesDomainActions` + bespoke `getHeaderActions()`.
