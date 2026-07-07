---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-07
---

# Hot Cache

## Last Updated
**2026-07-07 — `parties-module-k-br-guards` ralph loop RUNNING. Task 3.2 (Agreement-4 Club-active scoping guard) DONE, committed — 9/23.** Wired the 2.4-authored `ProducerAgreementClubNotActive::forClub(int, string)` into `CreateProducerAgreement` as the THIRD guard: a per-Club scope (`clubId` set) whose Club is `sunset`/`closed` is rejected INSIDE the txn (next to the Producer-existence check, reading the Club's `status` cast); Producer-wide (`clubId` NULL) ungated; a non-existent Club falls through to the FK backstop (only a real non-active Club trips it). Supersession exemption is 3.3's (activation path).

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Task 3.2 full loop **green**: focused `ProducerAgreementTest` 16/16 → SQLite full suite **2012/2012** (2008 baseline + 4 new cases) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid.
- **No PG17 run needed for 3.2** — adds NO schema/SQL (a within-module Eloquent read + a PHP guard; the Club `status` cast round-trip is baseline, already green on PG17). **PG17 recipe (for DB-schema tasks, 7.1 gate, CHECK reject-lanes):** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (`pg` container UP on :55432; NOT :5432). Full suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB).

## Active Change & Next Task
- **`parties-module-k-br-guards` — 9/23 done. NEXT = task 3.3 (RM-20):** cross-shape mutual-exclusion guard in `ActivateProducerAgreement` — reject activating a Producer-wide agreement while any per-Club agreement of that Producer is `active`, and vice versa, via `ProducerAgreementScopeConflict::producerWideBlockedByClubScope(int)|clubScopeBlockedByProducerWide(int)` (2.4 exception, call verbatim), pre-write in the txn (state + event log unchanged). Keep same-scope supersession intact; confirm a supersession whose Club has since `sunset` STILL activates (the Agreement-4 exemption lives on the activation side). **INVERT the shipped `ProducerAgreementLifecycleTest` L157+L206** ("both active" → "rejected"); same-scope supersession L95/L98 stays green; console `ProducerAgreementLifecycleConsoleTest` surfaces the conflict as a notification. Grep `grep -rn 'ActivateProducerAgreement' app/ tests/` + `callAction('activate')` FIRST.
- **Scope after 3.3:** §4 (4.1 RM-21 → `ClubNotAcceptingMemberships::forClub` in `CreateProfile` — use the 3.2 guard-placement pattern verbatim; 4.2 CreateProfile auto_renew inheritance + `SetProfileAutoRenew`; 4.3 Club-6 registration-flow + **invite_only PRE-SATISFIED by 2.3 EXCEPT lang EN/IT + `ClubConsoleI18nTest` L48/L62**; 4.4 RM-19 RetireProducer Profile cascade) → §5 (5.1 Identity-6 age-gate; 5.2 Producer-5 content lock) → §6 console+i18n → §7 close (human-gated).

## Blockers & Decisions Needed
- None. Change APPROVED; branch `ralph/parties-module-k-br-guards`.
- **Append memory files via the Edit tool, not `cat >>` heredocs** (git-guardrails hook false-positives on spec-path strings in the body).

## Open Patterns
(full form in progress.md `## Codebase Patterns`)
- **Business-rule guard on a related entity's state (3.2 — direct template for 4.1):** DB-read guard → INSIDE the txn next to the existence check (contrast 3.1's pure-input guard, BEFORE the txn). Read the cast via `Model::query()->whereKey($id)->first()` then `$m->status` (unambiguous enum); pass `$m->status->value` (non-PII) to the factory. Split non-existent (null → skip, FK backstop) from non-active-existing (`$m !== null && $m->status !== Active` → domain reject). Guard only on the optional-set branch (`if ($clubId !== null)`). RM-08 grep every caller first.
- **Value-domain reject ≠ business-rule guard (3.1)** + **cast blast-radius (2.1)** + **additive NOT-NULL needs DB default (2.2)** + **pre-launch column DROP edits create-table, atomic sweep (2.3)** + **localized guard exception = SoD shape (2.4)** — all in progress.md Codebase Patterns.
