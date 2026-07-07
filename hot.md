---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-07
---

# Hot Cache

## Last Updated
**2026-07-07 — `parties-module-k-br-guards` ralph loop RUNNING. Task 4.2 (Profile-5 auto_renew inheritance + `SetProfileAutoRenew`) DONE, committed — 12/23.** Two-part Profile-5 K-side: (1) `CreateProfile` sets `auto_renew` by inheriting the target Club's `auto_renew_default` at creation (reuses the `$club` the 4.1 guard fetched); (2) new operator Action `SetProfileAutoRenew` = the SOLE post-creation writer, audit-only (NO domain event — the `CancelProfile` precedent). Customer self-toggle stays a DEFERRED Consumer-Portal seam (proven absent by a code-surface writer-set scan).

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Task 4.2 full loop **green**: focused 27/27 → SQLite full suite **2029/2029** (2017 baseline + 12 new: ProfileTest +2 inheritance, ProfileAutoRenewTest +10) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid.
- **No PG17 run for 4.2** — adds NO schema/SQL (a within-module `lockForUpdate` read + an existing-column write; the `auto_renew`/`auto_renew_default` columns + boolean casts were up/down-verified on PG17 in 2.2). `LIKE '%auto_renew%'` event assertions are count-0 on both engines with a `count()===0` backstop. **PG17 recipe (DB-schema tasks, 7.1 gate):** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (`pg` container UP on :55432). Full suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB).

## Active Change & Next Task
- **`parties-module-k-br-guards` — 12/23 done. NEXT = task 4.3 (Club-6):** make `OpenRegistration` non-selectable (a create/update setting `open_registration` REJECTED; the three launch values admitted) + assert **no `registration_flow_type` value auto-approves** a membership (every value still needs `ApproveProfile` to reach `Active`). 2.3 already swept app/+database/ `invite_only` → 4.3's invite_only leg is ONLY the EN/IT lang keys + `ClubConsoleI18nTest` L48/L62 (shared with 6.4); the SUBSTANTIVE work is the registration-flow logic. Grep `ClubRegistrationFlowType` (`OpenRegistration='open_registration'` + 3 launch values) + `CreateClub`/`ClubResource` create surface first. _Acceptance:_ `ClubTest`/`ClubRegistrationFlowType` — three launch values admitted, `open_registration` rejected; membership under each launch value still needs `ApproveProfile`; the 5 touched Club test files green.
- **Scope after 4.3:** 4.4 RM-19 (RetireProducer Profile cascade) → §5 (5.1 Identity-6 age-gate; 5.2 Producer-5 content lock) → §6 console+i18n → §7 close (human-gated).

## Blockers & Decisions Needed
- None. Change APPROVED; branch `ralph/parties-module-k-br-guards`.
- **Append memory files via the Edit tool, not `cat >>` heredocs** (git-guardrails hook false-positives on spec-path strings).

## Open Patterns
(full form in progress.md `## Codebase Patterns`)
- **Audit-only operator preference writer + Larastan-non-null-`first()`→ternary (4.2):** the `CancelProfile` shape (txn + `lockForUpdate` + `update`, NO event, NO constructor deps, NO from-state guard for a preference); inherit-at-creation reusing a guard-fetched nullable `$parent` needs a `$parent === null ? default : $parent->col` TERNARY (not `?-> ?? default` — Larastan reads `first()` non-null → `nullsafe.neverNull` at max; the ternary is PHPStan-clean AND keeps the FK-backstop a clean QueryException). Adding a non-`Create*` Action reds `SupplyLifecycleChainTest`'s EXACT-SET whitelist (L418) → declare it in a concern-group; the other 3 Action-globbing tests are subset checks. Code-surface sole-writer = boot-free `glob(Actions/*.php)` for the `'col'` write-key.
- Earlier patterns (in progress.md): required-reference-guard UNCONDITIONAL (4.1) · business-rule guard on related-entity state (3.2) · behaviour-inversion guard (3.3) · value-domain-reject vs business-rule-guard (3.1) · cast blast-radius (2.1) · additive-NOT-NULL-needs-default (2.2) · column-DROP-atomic-sweep (2.3) · localized-guard-exception-SoD-shape (2.4).
