---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-07
---

# Hot Cache

## Last Updated
**2026-07-07 — `parties-module-k-br-guards`: task 6.4 DONE + committed. ALL LOOP TASKS COMPLETE (§1–§6 = 20/23). Loop emitted `<promise>CHANGE_COMPLETE</promise>`.** 6.4 (the LAST loop task) narrowed both Club create surfaces' registration-flow `Select` to the THREE launch channels, excluding the latent `open_registration`: a `->reject(fn = OpenRegistration)` on each surface's OWN `registrationFlowTypeOptions()` helper (`ClubResource` L309 raw-token labels + `ClubsRelationManager` L148 localized `registration_flow.*` labels — two duplicated copies, each label style preserved). The enum case STAYS (DB/domain-valid; 4.3's `Club::booted()` `saving` guard is the server floor — the console reject is defense-in-depth). Added `->default(ClubRegistrationFlowType::ApplicationWithApproval->value)` to both Selects (delta spec annotates `application_with_approval` "(default)"; the 6.1 operand-enum-default precedent; referenced the case `->value` directly — no Parties-enum edit). ZERO i18n change (invite_only + i18n legs were pre-done by 2.3/4.3; `registration_flow.open_registration` stays authored-but-latent). Tests: `ClubCreateConsoleTest` +2 (`Select $field` options === 3 launch values; default-when-untouched) + the field test gained `assertFormFieldDoesNotExist('invite_only')`.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Task 6.4 full loop **green**: focused `ClubCreateConsoleTest` 6/6 (49 assertions) → SQLite full suite **2079/2079** (2077 +2) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid.
- **No PG17 run for 6.4** — console/form only, NO schema/SQL. **PG17 recipe (for §7.1):** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (`pg` container on :55432). Full suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB).

## Active Change & Next Task
- **`parties-module-k-br-guards` — 20/23. §1–§6 COMPLETE. The ralph LOOP IS DONE (6.4 was the last loop task).** Branch `ralph/parties-module-k-br-guards`, all §1–§6 commits local.
- **REMAINING = §7 HUMAN-GATED close (§2.7 — NOT a loop task):** 7.1 full quality gate incl. the **PG17** engine + phpstan/pint/openspec; 7.2 traceability (every delta req → ≥1 test) + i18n sweep + 3 mini-ADRs consistent; 7.3 update `docs/validation/Remediation_Tracker.md` (RM-19/20/21/22/23 → ✅ w/ evidence; note J-15a / Producer-5-full / Profile-5-self-toggle deferrals), `hot.md`, `log.md`. A human runs these + review/merge/`openspec archive`.

## Blockers & Decisions Needed
- None. Change APPROVED; all §1–§6 green + committed. Awaiting the human §7 close (incl. the classifier-gated push to origin/main — ASK before pushing).
- **Append memory files via the Edit tool** (git-guardrails hook false-positives on spec-path strings in `cat >>` heredocs); a full hot.md rewrite via the Write tool is fine. progress.md Edits need a prior Read of the edit region (SessionStart injection does NOT satisfy it).

## Open Patterns
(full form in progress.md `## Codebase Patterns`)
- **Narrow an operand-enum console Select to exclude a LATENT case (6.4):** `->reject(fn = LatentCase)` in the options helper; KEEP the enum case + its authored i18n label (the model `saving` guard is the real floor; console reject is picker-only). Two duplicated create-surface helpers → apply to BOTH (parent `OperatorConsoleUiPassTest` covers the RM copy). When the delta spec annotates a value "(default)", set `->default(Enum::Case->value)` + a "defaults-when-untouched" test (the 6.1 idiom).
- Earlier §6: two-field create-rejection routing w/o importing the exception (6.3) · ZERO-page-code guard-surfacing + non-lifecycle preference affordance (6.2) · operand-enum Select + reactive active-picker w/ the ACTION as server floor (6.1). §2–5 (in progress.md): model `updating`/`saving`/`booted` guards (5.2/4.3), fail-fast boundary input-gate (5.1), cascade reusing audit-only Action (4.4), audit-only preference writer (4.2), required-reference-guard UNCONDITIONAL (4.1), value-domain-reject vs business-rule-guard (3.1).
