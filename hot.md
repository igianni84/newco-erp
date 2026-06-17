---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-17
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-17 (ralph — `parties-compliance` task 6.3 green → CHANGE COMPLETE, 11/11).** The slice's FINAL task: the whole-chain integration proof + cross-engine close. New test-only file `tests/Feature/Modules/Parties/ComplianceChainTest.php` (4 `it` → **8 tests / 46 assertions**). A global `runComplianceChain()` helper drives the ENTIRE slice through the real Actions in the task order: factory-born un-screened Customer+Producer → Customer KYC `RequireKyc`→`RecordKycVerified` → sanctions `RecordCustomerScreening(Passed,Onboarding)` then `(Passed,ComplianceAdHoc)` → Producer KYC `RequireProducerKyc`→`WaiveProducerKyc` → `ActivateProducer` through the new gate. Four angles: (a) end-states (customer kyc `verified`/sanctions `passed`/trigger ad-hoc/status pinned `pending`; producer kyc `not_required`/status `active`); (b) event-log shape — EXACTLY `{CustomerOnboardingScreeningPassed:1, CustomerRescreeningPassed:1, ProducerActivated:1}`, zero `%Kyc%`/`%Hold%`/demand-side/Account-Profile; (c) the producer-gate matrix driven through the REAL Producer-KYC Actions into the gate (cleared `verified`/`not_required`/NULL admit; `pending`/`rejected` block, stay `draft`, throw w/ `'KYC'`); (d) the asymmetric NULL (L1) — NULL-kyc Producer clears, NULL-sanctions Customer `≠ passed` until screened. **Factories (not Create* Actions) deliberately** — keeps the event log to exactly the compliance events (CreateCustomer co-provisions an Account). Zero production/protected/other-test edits.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 718/718 SQLite** (3361 assertions; 710 + 8 new). PHPStan max 0 · Pint clean · `openspec validate parties-compliance --strict` ✅ · `git diff main -- composer.json composer.lock` empty.
- **PG17 cross-engine close DONE: 168/168** (`tests/Feature/Modules/Parties` 160 + `tests/Architecture` 8, 1151 assertions) on `docker postgres:17` — incl. the asymmetric-NULL (L1) assertions (Producer-kyc-NULL-cleared AND Customer-sanctions-NULL-blocked) + the rejected-activation savepoint rollback. Container torn down.

## Active Change & Next Task
- **`parties-compliance` is COMPLETE — all 11 tasks `- [x]`.** Branch `ralph/parties-compliance`. Replied `<promise>CHANGE_COMPLETE</promise>`.
- **NOT archived/merged** (humans do that after review). Post-review close-out: human review → merge `ralph/parties-compliance` → semantic-verify (GUIDE §2.7) → `openspec archive parties-compliance --yes`.
- **No active change after this.** Next ralph run needs a fresh APPROVED change under `openspec/changes/` (none pending). Likely successors per the slice boundary: **`parties-holds`** (the unified Hold registry + the `kyc`-Hold coupling onto these KYC Actions — Action-orchestrated, KYC has no event) or **`parties-membership-lifecycle`** (the demand-side Customer/Account/Profile status FSMs). Run `/spec-to-change` to author.

## Blockers & Decisions Needed
- **None.** No open ADR gate crossed by this change. Cleared-state semantics fixed by ADR `2026-06-17-producer-kyc-gate-not-required-clears.md` (NULL ≡ cleared for additivity; `not_required` ≡ `verified`).

## Open Patterns
- **Compliance-chain integration-close idiom (6.3).** Drive the whole slice through real Actions via a typed `runComplianceChain()` helper; **create via FACTORIES** (CreateCustomer co-provisions an Account → would break the "no Account/Profile" assertion); pin the event log by distinct-name set + `%Kyc%`/`%Hold%` event-silence LIKE checks; the producer-gate matrix drives REAL Producer-KYC Actions into the gate keyed on a `?KycStatus`+`bool` dataset (no closures-in-datasets). **The chain test is the home for the asymmetric-NULL (L1) assertion** (both NULL meanings in one test). A compliance change's **PG17 close = `tests/Feature/Modules/Parties` + `tests/Architecture` together**.
- **PG17 gate recipe** (each DB task): `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; wait via Bash bg `until docker exec pg pg_isready -U newco -q; do sleep 0.5; done`; `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=512M vendor/bin/pest <paths>`; `docker rm -f pg`.
- **Full suite runner** = `php -d memory_limit=512M vendor/bin/pest` (plain `php artisan test` OOMs at 128M).
