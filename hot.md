---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-kyc-sanctions` — ralph loop RUNNING; task 1.2 done, 2/13).** Pinned the Filament 5.6.7 **page header-action visibility-test API** against the *installed* vendor source (not memory) + a throwaway probe (run green, then deleted). Confirmed: `assertActionVisible/Hidden('verb')` resolve a header action by name and evaluate its `->visible()` closure **per record**; the D4 hidden-action landmine applies to header actions (`callAction` asserts-visible-FIRST; `mountAction` is a no-op on a hidden action). Recorded the helpers in the `ViewCustomer` class docblock (comment-only, no behavior change).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (this session): full suite 1442/1442 (7948 assn, exit 0)** — SQLite. PHPStan max 0 errors; `pint --test` clean; `openspec validate --strict` valid. Diff is comment-only (`ViewCustomer` docblock); no `spec/`/`openspec/specs/`/`tests/Architecture/` touched, no composer dep, no migration.
- Full suite OOMs at PHP default 128 MB in result *parsing* (not a failure) — run pest with `php -d memory_limit=-1`. PG17 ritual is task 4.2 (not run yet this slice).

## Active Change & Next Task
- **`operator-console-parties-kyc-sanctions` — APPROVED, IN PROGRESS (2/13).** Delta on `operator-console`: 2 ADDED (KYC require/verify/reject; sanctions screening) + 2 MODIFIED Customer reqs; 13 tasks / 5 groups.
- **Next task 1.3:** front-load i18n. Extend the `customer` block in `lang/en/operator_console.php` AND `lang/it/operator_console.php` with 10 new keys (`actions.require_kyc`, `.record_kyc_verified`, `.record_kyc_rejected`, `.record_screening`; `fields.screening_verdict`, `.screening_source`; `notifications.kyc_required`, `.kyc_verified`, `.kyc_rejected`, `.screening_recorded`). Every IT value MUST differ from EN (the IT-differs guard). Add the 10 suffixes to `customerConsoleKitKeys()` in `CustomerConsoleI18nTest`. Then group 2 (KYC verbs).
- After all 13: review/merge → semantic-verify (GUIDE §2.7) → `openspec archive`.

## Blockers & Decisions Needed
- **None blocking task 1.3.** No open-ADR gate crossed (operator auth shipped; verbs invoke synchronous domain Actions).
- **Holds push gate STILL pending** (separate change) — origin/main push of the Holds archive+merge commits + `git branch -d ralph/operator-console-parties-holds` await Giovanni's go (classifier-gated). Local merge+archive already done.

## Open Patterns
- **Header-action visibility-test API now pinned** (in this change's `progress.md ## Codebase Patterns`): `assertActionVisible/Hidden`, mount path (`mountAction`+`assertFormFieldExists/Visible/Hidden`+`setActionData`), and the reject-via-domain-`toThrow`+`assertActionHidden` split (NEVER `assertNotified(action_failed)` for a hidden verb).
- **kyc-sanctions landmines (read design.md/tasks.md before coding):** (1) KYC verbs are **event-silent** — assert the coupled `CustomerHoldPlaced/Lifted` + `CustomerSuspended/Reactivated`, NEVER a KYC event (D7); (2) `KycStatus` = **state** enum (cast-value predicate `->value`, never imported); `SanctionsStatus`/`ScreeningTriggerSource` = **operand** enums (imported, the carve-out — `ModuleBoundariesTest` UNCHANGED); (3) reject = surface-hides + domain-`toThrow` (hidden-action landmine), not `action_failed`; (4) the chain-test asserts exactly 5 events (`toEqualCanonicalizing` + `toHaveCount(5)`).
