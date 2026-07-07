---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-07
---

# Hot Cache

## Last Updated
**2026-07-07 — `parties-module-k-br-guards` ralph loop RUNNING. Task 3.3 (RM-20 cross-shape mutual-exclusion) DONE, committed — 10/23. §3 (ProducerAgreement guards) COMPLETE.** Wired BR-K-Agreement-1 **clause 2** into `ActivateProducerAgreement`: Producer-wide (`club_id` NULL) and per-Club (`club_id` set) shapes are mutually exclusive on a Producer — activating one while the OPPOSITE shape is `active` is rejected pre-write with `ProducerAgreementScopeConflict::producerWideBlockedByClubScope|clubScopeBlockedByProducerWide(int)` (2.4 factories, 2/5 wired). This canon adoption INVERTS a shipped rule ("MAY both be active"), so it was as much inversion as addition.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Task 3.3 full loop **green**: focused lifecycle+console 20/20 → SQLite full suite **2015/2015** (2012 baseline + net 3 new) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid.
- **No PG17 run needed for 3.3** — adds NO schema/SQL (two within-module `exists()` reads + a PHP guard; `whereNull`/`whereNotNull` emit `IS NULL`/`IS NOT NULL`, engine-agnostic — not the `= NULL` trap; `club_id`/`status` casts baseline-exercised on PG17). **PG17 recipe (DB-schema tasks, 7.1 gate, CHECK reject-lanes):** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (`pg` container UP on :55432, NOT :5432). Full suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB).

## Active Change & Next Task
- **`parties-module-k-br-guards` — 10/23 done. NEXT = task 4.1 (RM-21):** Club-active guard in `CreateProfile` — a `sunset`/`closed` target Club → `ClubNotAcceptingMemberships::forClub(int, string $state)` (3rd 2.4 exception wired), no Profile/event. **Use the 3.2 guard-placement pattern VERBATIM** (business-rule guard on a related entity's state: DB-read INSIDE the txn next to the existence check; non-existent Club → skip to FK backstop; non-active existing → domain reject; pass `$club->status->value` non-PII). **Grep all `CreateProfile` callers (`app/ tests/ database/` + `callAction('create')`) FIRST** — the 4.1 ℹ note says all confirmed active. _Acceptance:_ `ProfileTest` — active admitted; sunset+closed rejected (no row/event); full suite green.
- **Scope after 4.1:** 4.2 Profile-5 (CreateProfile inherits Club `auto_renew_default` + `SetProfileAutoRenew` operator action, no self-toggle) → 4.3 Club-6 (OpenRegistration non-selectable + no-auto-approve; invite_only PRE-SATISFIED by 2.3 EXCEPT lang EN/IT + `ClubConsoleI18nTest` L48/L62) → 4.4 RM-19 (RetireProducer Profile cascade) → §5 (5.1 Identity-6 age-gate; 5.2 Producer-5 content lock) → §6 console+i18n → §7 close (human-gated).

## Blockers & Decisions Needed
- None. Change APPROVED; branch `ralph/parties-module-k-br-guards`.
- **Append memory files via the Edit tool, not `cat >>` heredocs** (git-guardrails hook false-positives on spec-path strings in the body).

## Open Patterns
(full form in progress.md `## Codebase Patterns`)
- **Behaviour-inversion guard (3.3, RM-20 — taxonomy-kind-(c)):** when canon FLIPS a shipped rule, invert every encoding of the OLD rule — shipped test bodies + Action docblock + test-file docblock — not just add the guard. Grep `both be active|MAY both|coexist` per design R3; triage unrelated-domain hits. Cross-shape guard = check OPPOSITE shape not active via `exists()` (no lock), INSIDE txn after from-state before clause-1 supersession; same-shape→supersede, opposite→reject, two-per-Club-diff-Club→permit; activation has NO Club-active check (Agreement-4 exemption). New RuntimeException surfaces as `action_failed` with zero console change.
- **Business-rule guard on related entity's state (3.2 → direct template for 4.1)** + value-domain-reject vs business-rule-guard (3.1) + cast blast-radius (2.1) + additive-NOT-NULL-needs-default (2.2) + column-DROP-atomic-sweep (2.3) + localized-guard-exception-SoD-shape (2.4) — all in progress.md Codebase Patterns.
