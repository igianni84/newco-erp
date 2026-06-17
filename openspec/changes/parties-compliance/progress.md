# Progress — parties-compliance

## Codebase Patterns
(consolidated reusable patterns — read first each iteration)

- **Compliance enums (task 1.1, shipped).** `app/Modules/Parties/Enums/{KycStatus,SanctionsStatus,ScreeningTriggerSource}.php` — all string-backed, PascalCase case names, persisted-token values.
  - `KycStatus` cases `NotRequired|Pending|Verified|Rejected` + **`clears(): bool`** → true for `Verified`/`NotRequired` only. The producer gate (task 5.1) is `$kyc === null || $kyc->clears()` — NULL is the absence of a case, handled at the gate, not in the enum.
  - `SanctionsStatus` cases `Pending|Passed|Failed|UnderReview`. **Completions = `Passed`/`Failed`** (each fires a § 15.6 event); `UnderReview` fires none.
  - `ScreeningTriggerSource` cases `Onboarding|Cadence|AmlThreshold|ComplianceAdHoc`. **`Onboarding` → `CustomerOnboardingScreening{Passed,Failed}`; every other source → `CustomerRescreening{Passed,Failed}`** (event-family selector for task 4.2).
- **Enum house style.** No methods on the parties-core enums; `clears()` is the first predicate. File docblock cites design L-tag + spec §. Tests pin **verbatim, order-sensitive** case→value maps + `toHaveCount` + a `from()` ValueError rejection — mirror `tests/Unit/Modules/Parties/Enums/EnumsTest.php`. New compliance enum test is a **separate** file `ComplianceEnumsTest.php` (don't amend `EnumsTest.php`).
- **Quality loop runners.** Full suite = `php -d memory_limit=512M vendor/bin/pest` (plain `php artisan test` OOMs at 128M). Filtered = pass the test file path. `vendor/bin/phpstan analyse --no-progress` (level max, 0 errors required); `vendor/bin/pint` to format, `vendor/bin/pint --test` to check. `openspec validate parties-compliance --strict` is a standing per-task gate (delta specs present).

---

## [2026-06-17 11:50] — 1.1 Compliance enums + the KYC cleared predicate
- **Implemented:** the three string-backed compliance enums with verbatim spec tokens — `KycStatus` (`not_required|pending|verified|rejected`) + the `clears()` cleared-state predicate (true for `verified`/`not_required` only — § 4.4 / design L1), `SanctionsStatus` (`pending|passed|failed|under_review` — § 9.2), `ScreeningTriggerSource` (`onboarding|cadence|aml_threshold|compliance_ad_hoc` — § 9.2 trigger paths, DEC-030/DEC-035).
- **Files changed:** `app/Modules/Parties/Enums/KycStatus.php`, `…/SanctionsStatus.php`, `…/ScreeningTriggerSource.php` (new); `tests/Unit/Modules/Parties/Enums/ComplianceEnumsTest.php` (new, 8 tests); `openspec/changes/parties-compliance/tasks.md` (1.1 checked).
- **Quality loop: green.** Pint ✅ · filtered test 8/8 ✅ · full suite **621/621** (613 baseline + 8) ✅ · PHPStan max 0 errors ✅ · Pint --test ✅ · `openspec validate --strict` ✅. No DB touched this task (PG17 gate N/A — first DB task is 1.2).
- **Learnings for future iterations:**
  - The cleared predicate lives on `KycStatus::clears()` so the producer gate (5.1) and the deferred customer purchase gate share one definition; NULL-as-cleared is a **gate** concern, not encoded in the enum (NULL = no case).
  - The event-family selector for sanctions (4.2) keys on `ScreeningTriggerSource`: `Onboarding` → onboarding events, anything else → rescreening events; `UnderReview` verdict records no event at all.
  - Verified the trigger tokens against PRD § 9.2 (lines 479–488): onboarding, 12-month `cadence`, `aml_threshold` (DEC-035), `compliance_ad_hoc`; country-change is explicitly NOT a launch trigger (used as the negative `from()` case).
---
