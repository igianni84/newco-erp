---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-kyc-sanctions` — ralph loop RUNNING; task 1.3 done, 3/13).** Front-loaded the 10 KYC/sanctions i18n keys into the `customer` block of BOTH `lang/en` and `lang/it` operator_console.php (every IT value distinct from EN — the IT-differs guard) AND added their suffixes to `customerConsoleKitKeys()` in `CustomerConsoleI18nTest`. Keys: `actions.{require_kyc,record_kyc_verified,record_kyc_rejected,record_screening}`, `fields.{screening_verdict,screening_source}`, `notifications.{kyc_required,kyc_verified,kyc_rejected,screening_recorded}` (`action_failed` reused). The i18n contract is now green ahead of the surface — groups 2–3 only wire behaviour.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (this session): full suite 1462/1462 (7988 assn, exit 0)** — SQLite (was 1442/7948; +20 data cases = 10 keys × 2 guards). PHPStan max 0 errors; `pint --test` clean; `openspec validate --strict` valid. Diff = i18n + test-enumeration only (`lang/{en,it}/operator_console.php`, `CustomerConsoleI18nTest.php`); no production source, no `spec/`/`openspec/specs/`/`tests/Architecture/`, no migration, no composer dep.
- Full suite OOMs at PHP default 128 MB in result *parsing* (not a failure) — run pest with `php -d memory_limit=-1`. `--filter` + phpstan run fine at default. PG17 ritual is task 4.2 (not run this slice).

## Active Change & Next Task
- **`operator-console-parties-kyc-sanctions` — APPROVED, IN PROGRESS (3/13).** Delta on `operator-console`: 2 ADDED (KYC require/verify/reject; sanctions screening) + 2 MODIFIED Customer reqs; 13 tasks / 5 groups. Group 1 (prep) DONE.
- **Next task 2.1:** append the three form-less KYC verbs to `ViewCustomer::getHeaderActions()` via the kit's `lifecycleAction($verb, $successKey, $invoke)` (`$form=null`), each chaining `->visible(...)` for its from-state gate. Add `private` cast-value predicates `kycRequirable(Customer)` (`kyc_status` null/`not_required`) + `kycPending(Customer)` (`pending`). Import the three Actions `App\Modules\Parties\Actions\{RequireKyc,RecordKycVerified,RecordKycRejected}`; do NOT import `KycStatus` (state enum, cast `->value` only) or `IllegalKycTransition` (name it in PROSE so Pint can't re-add the import — the 2026-06-20 landmine). Then 2.2/2.3 (write-through + reject floor), group 3 (sanctions form), group 4 (PG17 chain), group 5 (gates+memory).
- After all 13: review/merge → semantic-verify (GUIDE §2.7) → `openspec archive`.

## Blockers & Decisions Needed
- **None blocking task 2.1.** No open-ADR gate crossed (operator auth shipped; verbs invoke synchronous domain Actions).
- **Holds push gate STILL pending** (separate change) — origin/main push of the Holds archive+merge commits + `git branch -d ralph/operator-console-parties-holds` await Giovanni's go (classifier-gated). Local merge+archive already done.

## Open Patterns
- **i18n front-load discipline (now in this change's `progress.md ## Codebase Patterns`):** author EN+IT (IT≠EN) + the I18nTest key-contract in the group-1 prep, ahead of the surface; the `…ItDiffersKeys()` dataset derives via `array_diff(…, ['label','plural_label'])` so one list feeds both guards; inline sub-group comments per slice, lead comments untouched; run via `--filter`/full suite so `scanOperatorConsoleHardcodedSinks` loads.
- **Header-action visibility-test API pinned** (this change's `progress.md`): `assertActionVisible/Hidden`, mount path (`mountAction`+`assertFormFieldExists/Visible/Hidden`+`setActionData`), reject-via-domain-`toThrow`+`assertActionHidden` (NEVER `assertNotified(action_failed)` for a hidden verb).
- **kyc-sanctions landmines (read design.md/tasks.md before coding):** (1) KYC verbs are **event-silent** — assert the coupled `CustomerHoldPlaced/Lifted` + `CustomerSuspended/Reactivated`, NEVER a KYC event (D7); (2) `KycStatus` = **state** enum (cast `->value`, never imported); `SanctionsStatus`/`ScreeningTriggerSource` = **operand** enums (imported — the carve-out, `ModuleBoundariesTest` UNCHANGED); (3) reject = surface-hides + domain-`toThrow` (D4), not `action_failed`; (4) the chain-test asserts exactly 5 events (`toEqualCanonicalizing` + `toHaveCount(5)`).
