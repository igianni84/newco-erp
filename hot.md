---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-17
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-17 (ralph — `parties-compliance` task 1.1 green).** Shipped the three compliance enums that the rest of the slice keys on: `KycStatus` (`not_required|pending|verified|rejected`) **+ the `clears()` cleared-state predicate** (true for `verified`/`not_required` only — § 4.4 / design L1), `SanctionsStatus` (`pending|passed|failed|under_review`), `ScreeningTriggerSource` (`onboarding|cadence|aml_threshold|compliance_ad_hoc`). All string-backed, verbatim spec tokens, in `app/Modules/Parties/Enums/`. No DB, no events yet — pure value domains.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Green: 621/621 SQLite** (613 baseline + 8 new enum tests). PHPStan max 0 errors · Pint clean · `openspec validate parties-compliance --strict` ✅. Task 1.1 committed on `ralph/parties-compliance`. **PG17 not yet exercised this slice** (1.1 is non-DB; first DB task is 1.2).

## Active Change & Next Task
- **ACTIVE + APPROVED: `parties-compliance`** (11 tasks, 1/11 done). Branch `ralph/parties-compliance`.
- **Next (1.2):** the one additive migration `2026_06_17_000001_add_compliance_to_parties.php` — 8 nullable cols on `parties_customers` (kyc_status, kyc_required, enhanced_kyc_flag, enhanced_kyc_at, sanctions_status, last_screening_at, next_rescreen_at, screening_trigger_source) + 1 on `parties_producers` (kyc_status). All nullable/no-default/no-backfill (DEC-071); PG-only `CHECK (col IS NULL OR col IN (Enum::cases()))` on the 3 value-set cols per table; add casts to Customer/Producer models. **Reuse the `2026_06_15_000005_create_parties_customers_table.php` CHECK-from-`Enum::cases()` idiom** (pgsql driver guard).
- **Watch-it for 1.2:** the asymmetric NULL (Producer-KYC-NULL=cleared vs Customer-sanctions-NULL=blocked) MUST be asserted **on PG17** — SQLite-green is necessary, never sufficient. PG17 via docker `postgres:17` per `knowledge/testing/rules.md`.

## Blockers & Decisions Needed
- **None.** No open ADR gate crossed (events inline; screening manual-first; no KYC-doc storage). Slice boundary (Hold → `parties-holds`) ratified; cleared-state semantics fixed by ADR 2026-06-17.

## Open Patterns
- **Compliance-enum idiom (1.1):** `clears()` is the first method on a Parties enum; cleared = `Verified ∨ NotRequired`; NULL-as-cleared is a **gate** concern (5.1), not in the enum. Completions = `Passed`/`Failed` (fire events); `UnderReview` fires none. `Onboarding` source → onboarding event family, all others → rescreening (selector for 4.2). Details in `progress.md` Codebase Patterns.
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (plain `php artisan test` OOMs at 128M). Filtered = pass the file path.
- **PG17 gate** before a DB task is done: `docker run -d --name pg … postgres:17`; `DB_CONNECTION=pgsql … -p 55432`; `docker rm -f pg`.
- **`knowledge/`: architecture 2 · data-model 2+1 · laravel 3+1 · testing 4+1.** Promotion bar ≥3 dated cross-change confirmations.
- **Heredoc `cat <<EOF` mentioning "spec" trips the git-guardrails Bash hook** — write via Edit/Write or `scripts/memlog.sh`.
