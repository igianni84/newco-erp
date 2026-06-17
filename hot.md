---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-17
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-17 (ralph — `parties-compliance` task 6.2 green).** Docs-only close of the compliance vocabulary in **`CONTEXT.md`** (glossary of record, not protected; sanctioned by RALPH "WHEN APPROPRIATE"). Six surgical edits mirroring the `parties-producer-lifecycle` 5.1 precedent (`git show 1b34a64`): (1) Parties intro — compliance lifecycles now ship via `parties-compliance`, demand-side **status** stays deferred; (2) **Customer** term — two compliance-screening FSMs separate from `status` and each other (KYC + sanctions field lists); (3) **Producer** term — activation **now enforces the KYC-cleared gate** (`verified`/`not_required`/**NULL** clear; `pending`/`rejected` block), provenance `kyc_status` distinct, no event; (4) NEW subsection **"Parties compliance screening events — payload contract"** — the 4-event table (`CustomerOnboardingScreening{Passed,Failed}`, `CustomerRescreening{Passed,Failed}`, all `entity_type` `Customer`, keys `customer_id`/`sanctions_status`/`trigger_source` copied **verbatim** from the `Events/*.php payload()` bodies) + PII-free prose + key-naming note (`trigger_source` ← column `screening_trigger_source`) + `under_review`/KYC event-silence + the **Four deferred seams** (`kyc` Hold→`parties-holds`; sanctions order-completion→Module S §9.3; enhanced-KYC+cadence/AML automation; KYC docs→object-storage); (5) Compliance & Finance — replaced the `KYC / Sanctions screening` stub with **four** anchored headwords (**KYC lifecycle**, **KYC cleared state** incl. NULL-cleared + the asymmetric Customer-sanctions-NULL-blocked, **Sanctions screening lifecycle**, **Screening trigger source**); (6) ONE Catalog tense fix — the "KYC-on-activation → `parties-compliance`" seam bullet `when it lands`→`now landed`.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green: 710/710 SQLite** (3315 assertions, **unchanged** — docs add no tests). PHPStan max 0 · Pint clean · `openspec validate parties-compliance --strict` ✅ · `git diff main -- composer.json composer.lock` empty · only `CONTEXT.md` + `tasks.md` changed. Task 6.2 committed on `ralph/parties-compliance`.
- **PG17 gate correctly skipped for 6.2** — docs-only, no DB touched (matches `parties-producer-lifecycle` 5.1). 6.3 will run the cross-engine close on `postgres:17`.

## Active Change & Next Task
- **ACTIVE + APPROVED: `parties-compliance`** (11 tasks, **10/11 done**). Branch `ralph/parties-compliance`.
- **Next (6.3 — FINAL):** full compliance-chain feature test `tests/Feature/Modules/Parties/ComplianceChainTest.php` driving the whole slice: create Customer + Producer → require/verify Customer KYC → onboarding screen (passed) + ad-hoc re-screen → require/waive Producer KYC → activate through the **new** KYC gate (and assert a `pending`-KYC Producer is **blocked**). Assert `domain_events` holds **only** the expected `CustomerOnboardingScreening*`/`CustomerRescreening*` names (zero KYC/Hold) with exact counts; producer gate admits cleared/NULL, blocks pending/rejected; demand-side inert. **Then the cross-engine close: the ENTIRE Parties suite + the two arch tests on PostgreSQL 17** (incl. the asymmetric-NULL assertions — L1; record the PG run in `progress.md`). DB-touching → PG17 gate mandatory. Completing it → all 11 done → reply `<promise>CHANGE_COMPLETE</promise>` (do NOT archive/merge — humans do that).

## Blockers & Decisions Needed
- **None.** No open ADR gate crossed. Cleared-state semantics fixed by ADR `2026-06-17-producer-kyc-gate-not-required-clears.md` (NULL ≡ cleared for additivity; `not_required` ≡ `verified`).

## Open Patterns
- **Docs-pass scope rule (6.2).** A docs pass for change X updates (a) the terms/sections X changes and (b) any *other* section X renders factually stale — but does NOT restructure other changes' sections beyond removing staleness. Here that admitted exactly ONE Catalog edit ("when it lands"→"now landed" on the seam this change closes) and left the "Producer activation gate" term alone (still true). **Payload keys are copied from the real `Events/*.php payload()` bodies, never guessed** (grep `=>` first); document the published-key-vs-column divergence (`trigger_source` ← `screening_trigger_source`).
- **Independence/scope-guard test idiom (6.1).** Emergent-contract test = state-pair matrix (dataset) + dynamic cross-transition sequence + reflection scope guard. **Exact-set whitelist has ONE home (`SupplyLifecycleChainTest`)** — companions use a forbidden-name negative check. OC-no-setter source-scan targets `"'originating_club_id' =>"` (array-key write), NOT the bare column.
- **PG17 gate** each DB task: `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; poll `docker exec pg pg_isready -U newco`; `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=512M vendor/bin/pest tests/Feature/Modules/Parties`; `docker rm -f pg`.
