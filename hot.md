---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-01 (`parties-anonymisation`) task 6.1 ✅ — `Anonymise` + `Export` on the Customer console. 11 of 12 done.** Surfaced the two demand-side GDPR verbs on the `ViewCustomer` page via the shared kit's `SurfacesDomainActions::lifecycleAction()` (the operator twin of the 3.2 erasure + 5.1 export Actions). **`anonymise`** → write-through to `AnonymiseCustomer`, **visibility-gated** by the new private `notYetAnonymised()` (`anonymised_at === null`) — the IDEMPOTENCY gate (hidden once erased). It is NOT the complement of the domain guard: a `compliance`-Hold block is a RUNTIME rejection (`AnonymisationBlockedByComplianceHold`, a `RuntimeException`), so a not-yet-anonymised but `compliance`-held Customer keeps the verb VISIBLE and its block surfaces as `action_failed` on click (the `activate` cross-slice-gate precedent). **`export`** → write-through to the read-only `ExportCustomerData`, **UNGATED** (an anonymised Customer still exports placeholder PII); the in-memory payload is assembled + discarded by the surface (`surfaceLifecycleOutcome` ignores the return — the download vehicle is the deferred J-9b follow-up). New test `CustomerAnonymisationConsoleTest.php` (6 `it()`/7 cases). **i18n:** +4 keys in `lang/{en,it}` + registered in `CustomerConsoleI18nTest::customerConsoleKitKeys()`. **Live-verified headless** (no browser tooling): serve+curl 302-to-login (no 500) + a throwaway authenticated `->get()->assertSee()` render probe confirmed both buttons render / `anonymise` gate-suppressed, then deleted. **Next = task 7.1: PG17 + full close ritual.**

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- **Green:** SQLite full suite **1883/1883** (10189 assertions; +15 tests/+71 vs the 1868 task-5.1 baseline); PHPStan max **0**; Pint clean; `openspec validate parties-anonymisation --strict` valid.
- Full suite: `php -d memory_limit=-1 vendor/bin/pest` (bare `php artisan test` OOMs at 128M). **PG17 cross-engine run is task 7.1** (close ritual). The console edit adds only Filament header actions (no new Action, no migration) → `SupplyLifecycleChainTest` untouched.

## Active Change & Next Task
- **Active: `parties-anonymisation` (RM-01) — APPROVED ✅, BUILDING. 11/12 done.** Branch `ralph/parties-anonymisation`.
- **NEXT: task 7.1 — Full-suite + PHPStan + Pint + PG17 close** (all decisions; spec — all four requirements). Run the parties + operator-console suites on SQLite AND PG17; confirm no exhaustive Action allow-list reds; confirm the migrations are Postgres-truthful; verify the audit-redaction before/after UPDATE + the Hold read + the anonymisation orthogonality behave identically on PG17; `openspec validate --strict` green. **On green, ALL 12 tasks done → reply `<promise>CHANGE_COMPLETE</promise>`** (do NOT archive/merge — humans do that).

## Blockers & Decisions Needed
- **None.** RM-01 APPROVED; the `compliance`-only / count-independent gate is reconciled in ADR `2026-07-02-adopt-dec-015-…` (cite it, not the self-contradictory raw spec).
- **Incidental (candidate F4):** `party-registry` truth-spec *Hold Registry* still says "six-value" while code is **8** (RM-04 debt). RM-01's gate is `compliance`-only / count-independent → does NOT block.

## Open Patterns
- **Console verb via the kit (task 6.1, new pattern):** ONE `lifecycleAction(verb, successKey, fn => app(Action)->handle($id))` line surfaces any bare-`int $id` Parties Action; a READ-ONLY Action fits too (return discarded). **Visibility-gate vs runtime-rejection axis:** idempotency/already-done HIDES the verb (undrivable — never `callAction` a hidden verb); a SEPARATE runtime gate (a Hold) stays VISIBLE + surfaces `action_failed`. Imports stay `{Models, Actions, Enums}`; exception types in PROSE only.
- **A new console verb reds the i18n key-CONTRACT, not an action-set test:** add `actions.*`+`notifications.*` to `customerConsoleKitKeys()` AND author EN+IT (IT ≠ EN). The sink-scanner case (`scanOperatorConsoleHardcodedSinks`) is FULL-SUITE-only — a bare-path run reds it (isolation artifact).
- **Headless live-verify substitute (no browser tooling):** serve+curl 302 (page boots, no 500) + a throwaway authenticated `->get()->assertSee(label)->assertDontSee(hidden-label)` render probe (full HTTP→Filament→Blade), then delete — `assertActionVisible`/`callAction` stays the durable proof.
- **Anonymisation gate = `compliance`-only, count-independent:** key on `HoldType::Compliance` via `PartyComplianceStatusReader`; never the `Hold` model; never wire `sanctions_status`.
