---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-21
---

# Hot Cache

## Last Updated
**2026-06-21 (`operator-console-parties-customer` — GROUP 3 GREEN: Customer status-FSM verbs).** Filled the bare `ViewCustomer` stub with the full demand-side status FSM: `extends ViewRecord` + `use SurfacesDomainActions` (trait-level, NOT `OperatorConsoleViewRecord` — D1/D8), `i18nKey()='customer'`, and `getHeaderActions()` = four FORM-LESS, no-`confirmationKey` verbs via `lifecycleAction` (activate/suspend/reactivate/close → `app(<Action>::class)->handle($this->recordOf(Customer::class,$r)->id)`, each closure `(Model $record, string $notes)`, `$notes` unused). NO Hold/KYC/account/profile verb. + EN/IT `actions.{activate,suspend,reactivate,close}` + `notifications.{activated,suspended,reactivated,closed,action_failed}` + `CustomerLifecycleConsoleTest`. Tasks 3.1/3.2 done (7 of 9).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: SQLite 1342/1342 (7520 assn, +9 tests/+96 assn), phpstan 0, pint clean, `ModuleBoundariesTest` 3/3 (189 assn) UNCHANGED (no widening — verbs import `Parties\Actions\*` + `Parties\Models\Customer`, within {Models,Actions}), validate --strict valid, composer diff vs main empty.** (No PG17 run this group — scoped to group 5 chain.)
- **main is 2 commits ahead of origin/main, UNPUSHED** (supply-side merge+archive; push classifier-denied earlier) — still deferred to human.
- Run-cmd: full suite `php -d memory_limit=-1 vendor/bin/pest`; filter `--filter=CustomerLifecycleConsoleTest`. PG17 chain command in tasks.md preamble (group 5).

## Active Change & Next Task
- **Active: `operator-console-parties-customer` (7 of 9 tasks done).** Loop continues.
- **Next = Group 4 (task 4.1): `CustomerConsoleI18nTest`** — enumerate the full kit-contract keys this console resolves by string concat: resource `label`/`plural_label`/`columns.{name,email,status,kyc_status,sanctions_status,account_status,profiles,version}`; the four verbs `actions.{activate,suspend,reactivate,close}` + `actions.create`; `notifications.{activated,suspended,reactivated,closed,action_failed}`; create `fields.*`. FIVE guards mirroring `ProducerConsoleI18nTest`: EN baseline (`Lang::has(..., 'en', false)`), IT-differs over kit-minus-`{label,plural_label}` (authored in `it` AND != en), `label`/`plural_label` EN-fallback, IT⊆EN filtered `str_starts_with($dotKey,'customer.')`, and `scanOperatorConsoleHardcodedSinks()` behind `function_exists` scoped to `CustomerResource*`. **Run via `--filter=CustomerConsoleI18nTest` or the folder-wide run (NOT a bare file path — helper-load false-red).** Then group 5 = `CustomerConsoleChainTest` (PG17, D9).

## Blockers & Decisions Needed
- **Push decision (human, carried over):** main holds supply-side merge+archive locally; origin/main not updated.
- Otherwise none. (D5 landmine RESOLVED in group 3 — gate-unmet activate rejecting with no event is correct; the lifecycle test seeds gate-met via the 4 onboarding fields + sanctions=Passed, kyc_required null.)

## Open Patterns
- **Non-catalog status-FSM view page = `ViewRecord` + `use SurfacesDomainActions` + bespoke `getHeaderActions()`** — proven TWICE now (Producer + Customer). `lifecycleAction(verb, successKey, fn (Model, string) => app(Action)->handle(recordOf(Model,r)->id))`, form-less, no `confirmationKey`; Parties actions take `int $id`. **D8 rule-of-three CLOSED to this shape.** `OperatorConsoleViewRecord` imported ONLY for the `{@see}` docblock (pint counts it used).
- **Cross-slice gate (D5) is a test-seed concern, not code.** Factory is event/Account/Profile-free → rejected verbs leave `DomainEvent::count()===0`; profile-less Customer keeps suspend/reactivate cascade-silent.
- **i18n: all keys now authored** (group 3 added the verb actions/notifications). **IT 'Indirizzo email'** breaks the EN/IT 'Email' collision (group-4 IT-differs guard needs IT != EN). Recompute the group-4 kit count from the full authored set. Full Codebase Patterns in the change's `progress.md`.
