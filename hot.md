---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-07
---

# Hot Cache

## Last Updated
**2026-07-07 — `parties-module-k-br-guards` ralph loop RUNNING. Task 3.1 (RM-22 cadence closed-set reject) DONE, committed — 8/23.** Wired the server-side out-of-set/typo settlement-cadence rejection into the `CreateProducerAgreement` action: resolve `?string $settlementCadence` via `SettlementCadence::tryFrom()`, reject a non-null out-of-set token with a NEW localized `InvalidSettlementCadence` (value-domain shape — the `InvalidAddressCountryCode` precedent, NOT one of the 2.4 five) BEFORE the write, ahead of the raw `ValueError`. Null stays null.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Task 3.1 full loop **green**: focused `ProducerAgreementTest` 12/12 → SQLite full suite **2008/2008** (2004 baseline + 4 new cases) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid · DemoSeeder SQLite smoke exit 0.
- **No PG17 run needed for 3.1** — it touches NO DB (no migration, no new SQL; the reject is pre-DB via `tryFrom()`). **PG17 recipe (for 3.2+ DB-reads, 7.1 gate, CHECK reject-lanes):** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (`pg` container UP on :55432; NOT :5432). Full suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB).

## Active Change & Next Task
- **`parties-module-k-br-guards` — 8/23 done. NEXT = task 3.2 (Agreement-4):** Club-active scoping guard in the SAME `CreateProducerAgreement` path — a per-Club scope (`club_id` set) whose Club is `sunset`/`closed` → `ProducerAgreementClubNotActive::forClub(int, string $state)` (the 2.4 exception, call verbatim); Producer-wide (`club_id` NULL) ungated. _Acceptance:_ `ProducerAgreementTest` — active-Club scope admitted, sunset/closed rejected (no row/event), Producer-wide admitted (supersession-inherits-scope exemption is 3.3's). **Needs a DB read of the target Club's status → lives INSIDE the transaction** next to the Producer-existence check (contrast 3.1's pure-input cadence guard, which fails fast BEFORE the txn). Bump the action docblock "Two guards" → "Three".
- **Scope after 3.2:** 3.3 RM-20 → `ProducerAgreementScopeConflict::producerWideBlockedByClubScope|clubScopeBlockedByProducerWide` in `ActivateProducerAgreement`, inverts shipped `ProducerAgreementLifecycleTest` L157+L206 → §4 (4.1 RM-21 → `ClubNotAcceptingMemberships::forClub`; 4.2 CreateProfile auto_renew inheritance; **4.3/6.4 invite_only PRE-SATISFIED by 2.3 EXCEPT lang EN/IT + `ClubConsoleI18nTest` L48/L62**) → §5 (5.1 Identity-6; 5.2 Producer-5) → §6 console+i18n → §7 close (human-gated).

## Blockers & Decisions Needed
- None. Change APPROVED; branch `ralph/parties-module-k-br-guards`.
- **Append memory files via the Edit tool, not `cat >>` heredocs** (git-guardrails hook false-positives on spec-path strings in the body).

## Open Patterns
(full form in progress.md `## Codebase Patterns`)
- **Value-domain reject ≠ business-rule guard (3.1):** an out-of-set operand (bad cadence/country code) = the `InvalidAddressCountryCode` shape (`Invalid*` `RuntimeException`, `forX(string)→__()`, thrown at the boundary BEFORE the txn, value interpolated). A state/relationship violation = the SoD/2.4-five shape (thrown INSIDE the txn after a DB read). Delta-spec tell: a scenario NAMING an exception = business-rule guard; one that just says "rejected server-side" = value-domain reject (author a fresh `Invalid*` THIS task). Resolve `?string`→enum via `tryFrom($x) ?? throw`, pass the enum instance to `create()` (cast stores `->value`). Grep every Action caller for now-invalid literals first (RM-08).
- **Cast blast-radius (2.1)** + **additive NOT-NULL needs DB default (2.2)** + **pre-launch column DROP edits create-table in place, atomic sweep (2.3)** + **localized guard exception = SoD shape (2.4)** — all still live in progress.md Codebase Patterns.
