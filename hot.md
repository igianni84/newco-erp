---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-kyc-sanctions` — ralph loop RUNNING; task 2.1 done, 4/13).** Wired the three form-less, visibility-gated KYC verbs onto `ViewCustomer::getHeaderActions()`: `requireKyc` / `recordKycVerified` / `recordKycRejected`, each built with the kit's `lifecycleAction($verb, $successKey, $invoke)` (form-less) + a chained `->visible(fn (): bool => $this->predicate(...))` from-state gate. Added two `private` cast-value predicates — `kycRequirable(Customer)` (`kyc_status` null/`not_required`, non-nullsafe) and `kycPending(Customer)` (`?->value === 'pending'`, nullsafe). Imported only the three Actions; `KycStatus` cast-only, `IllegalKycTransition` in PROSE (the 2026-06-20 Pint landmine). New `CustomerKycSanctionsConsoleTest` pins the VISIBILITY contract (2.2/2.3 append to it).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (this session): full suite 1472/1472 (8018 assn, exit 0)** — SQLite (was 1462/7988; +10 visibility data cases). PHPStan max 0 errors (no `nullsafe.neverNull`); `pint --test` clean; `openspec validate --strict` valid. Diff = `ViewCustomer.php` (3 imports + 3 verbs + 2 predicates + docblocks) + new `CustomerKycSanctionsConsoleTest.php`; no `spec/`/`openspec/specs/`/`tests/Architecture/`, no migration, no composer dep. `ModuleBoundariesTest` UNCHANGED (no new operand-enum import this task).
- Full suite OOMs at PHP default 128 MB in result *parsing* (not a failure) — run pest with `php -d memory_limit=-1`. `--filter` + phpstan run fine at default. PG17 ritual is task 4.2 (not run this slice).

## Active Change & Next Task
- **`operator-console-parties-kyc-sanctions` — APPROVED, IN PROGRESS (4/13).** Delta on `operator-console`: 2 ADDED (KYC require/verify/reject; sanctions screening) + 2 MODIFIED Customer reqs; 13 tasks / 5 groups. Groups 1 (prep) + 2.1 (KYC verb visibility) DONE.
- **Next task 2.2:** test the KYC write-through + auto-Hold coupling in `CustomerKycSanctionsConsoleTest`: (a) `callAction('requireKyc')` on an **active** Customer → `kyc_status=pending`, a `kyc` Hold `active`, Customer `suspended`; exactly one `CustomerHoldPlaced` + one `CustomerSuspended`, ZERO `CustomerKyc%` events (D7); (b) arrange the verify-path Customer **through the real `RequireKyc` Action** (never the bare factory — it skips the coupling), then `callAction('recordKycVerified')` → `verified`, Hold `lifted`, Customer `active`, one `CustomerHoldLifted` + one `CustomerReactivated`; (c) `callAction('recordKycRejected')` on a `pending`+`kyc`-Hold Customer → `rejected`, Hold STILL `active`, NO event at all. Assert each success notification. Then 2.3 (reject floor), group 3 (sanctions form), group 4 (PG17 chain), group 5 (gates+memory).
- After all 13: review/merge → semantic-verify (GUIDE §2.7) → `openspec archive`.

## Blockers & Decisions Needed
- **None blocking task 2.2.** No open-ADR gate crossed (operator auth shipped; verbs invoke synchronous domain Actions).
- **Holds push gate STILL pending** (separate change) — origin/main push of the Holds archive+merge commits + `git branch -d ralph/operator-console-parties-holds` await Giovanni's go (classifier-gated). Local merge+archive already done.

## Open Patterns
- **Form-less visibility-gated verb** (now in this change's `progress.md ## Codebase Patterns`): bare-`int $id` Action → `lifecycleAction` + chained `->visible()` reading `$this->getRecord()`; predicate the STATE enum via the model CAST VALUE (never imported — D5), the EXACT COMPLEMENT of the domain guard so reject is surface-unreachable. Prove reject via domain `toThrow` + `assertActionHidden`, never `action_failed` (the hidden-action landmine).
- **kyc-sanctions landmines (read design.md/tasks.md before coding):** (1) KYC verbs are **event-silent** — assert the coupled `CustomerHoldPlaced/Lifted` + `CustomerSuspended/Reactivated`, NEVER a KYC event (D7); (2) `KycStatus` = **state** enum (cast `->value`, never imported); `SanctionsStatus`/`ScreeningTriggerSource` = **operand** enums (imported in 3.x — the carve-out, `ModuleBoundariesTest` UNCHANGED); (3) reject = surface-hides + domain-`toThrow` (D4), not `action_failed`; (4) the chain-test (4.1) asserts exactly 5 events (`toEqualCanonicalizing` + `toHaveCount(5)`).
- **i18n front-load discipline** (this change's `progress.md`): the 10 KYC/sanctions keys are already authored EN+IT (IT≠EN) — groups 2–3 only wire behaviour.
