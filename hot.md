---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-07
---

# Hot Cache

## Last Updated
**2026-07-07 — `parties-module-k-br-guards` ralph loop RUNNING. Task 4.1 (RM-21 Club-active guard in `CreateProfile`) DONE, committed — 11/23.** Wired BR-K-Club-3 / AC-K-FSM-6: a `CreateProfile` against a `sunset`/`closed` Club is rejected pre-write with the 2.4 `ClubNotAcceptingMemberships::forClub(int, string)` (3rd of 5 exceptions wired) — no Profile, no `ProfileCreated` event. Applied the 3.2 guard mechanics but UNCONDITIONAL (`clubId` is a REQUIRED int, not nullable → no `if(!==null)` wrapper); placed FIRST in the txn, before the BR-K-Identity-2 uniqueness check (blanket target gate precedes per-pair check). `auto_renew` inheritance deliberately NOT built (that's 4.2).

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Task 4.1 full loop **green**: focused `ProfileTest` 10/10 → SQLite full suite **2017/2017** (2015 baseline + 2 new dataset cases) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid.
- **No PG17 run needed for 4.1** — adds NO schema/SQL (one within-module `Club::query()->whereKey()->first()` PK read + a PHP guard; `whereKey` + the `status` cast are engine-agnostic and baseline-exercised on PG17). **PG17 recipe (DB-schema tasks, 7.1 gate, CHECK reject-lanes):** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (`pg` container UP on :55432, NOT :5432). Full suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB).

## Active Change & Next Task
- **`parties-module-k-br-guards` — 11/23 done. NEXT = task 4.2 (Profile-5):** `CreateProfile` sets `auto_renew` = the target Club's `auto_renew_default` at creation (columns + `true` floor already exist from 2.2) + add operator Action `SetProfileAutoRenew` (sole writer, audit-only, **NO domain event** — assert `%auto_renew%` produces no event via the event-ownership pattern); the customer self-toggle is NOT built (Consumer-Portal seam). **Reuse the `$club` the 4.1 guard already fetches** — add `'auto_renew' => $club->auto_renew_default` to `CreateProfile`'s `create([...])` array, don't re-query. _Acceptance:_ `ProfileTest`/`ProfileAutoRenewTest` — inherit true/false from Club default; operator override flips + persists (no event); a code-surface assertion that no Consumer-Portal write exists.
- **Scope after 4.2:** 4.3 Club-6 (OpenRegistration non-selectable + no-auto-approve; invite_only PRE-SATISFIED by 2.3 EXCEPT lang EN/IT + `ClubConsoleI18nTest` L48/L62) → 4.4 RM-19 (RetireProducer Profile cascade) → §5 (5.1 Identity-6 age-gate; 5.2 Producer-5 content lock) → §6 console+i18n → §7 close (human-gated).

## Blockers & Decisions Needed
- None. Change APPROVED; branch `ralph/parties-module-k-br-guards`.
- **Append memory files via the Edit tool, not `cat >>` heredocs** (git-guardrails hook false-positives on spec-path strings in the body).

## Open Patterns
(full form in progress.md `## Codebase Patterns`)
- **Required-reference guard = UNCONDITIONAL (4.1, RM-21):** when the business-rule-guard-on-a-related-entity mechanics (3.2) land on a REQUIRED (non-nullable) reference, drop the `if(!==null)` wrapper — always read + check. A BLANKET target-entity gate ("is this Club accepting memberships at all?") precedes a SPECIFIC per-pair guard ("does this customer already have one?"). Zero-blast-radius = the full suite (a broken admit path reds every default-active-factory test at once) → one reject-dataset test suffices.
- **Business-rule guard on related entity's state (3.2, template for 4.1 — now applied)** + behaviour-inversion guard (3.3) + value-domain-reject vs business-rule-guard (3.1) + cast blast-radius (2.1) + additive-NOT-NULL-needs-default (2.2) + column-DROP-atomic-sweep (2.3) + localized-guard-exception-SoD-shape (2.4) — all in progress.md Codebase Patterns.
