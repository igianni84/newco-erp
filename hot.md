---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-19
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-19 (ralph — `parties-membership-suspension` task 5.1 GREEN + committed → CHANGE COMPLETE, all 11/11 tasks done).** Shipped the slice's emergent-contract integration proof + docs + the cross-engine close. New `MembershipSuspensionChainTest` (4 `it()`s on a shared `runMembershipSuspensionChain()` builder) drives the WHOLE slice end-to-end through the REAL Actions on one Customer with four Profiles: Customer real spine, four memberships create→approve→activate; P_a suspend→restore + lapse→renew(grace); P_c lapse→cancel (terminal); P_d deactivate; the Hold-driven coupling (Profile Hold on P_b + Customer Hold cascade; lift Customer Hold restores C + cascade-restores P_a but **P_b stays Suspended** under its own Hold; lift P_b's Hold restores it); the audit-only Account FSM; `CloseCustomer`. Asserts the **EXACT 29-event multiset** (`toEqualCanonicalizing`) + 11-name forbidden-absent loop + `entity_type=Account` 0; the causation-child split (the lone cascade `ProfileSuspended` carries the `CustomerSuspended` `causation_id`, the other two are roots); every from-state guard incl. past-grace renewal. **Docs:** extended `CONTEXT.md` — corrected the stale Customer/Account/Profile/Hold entries + 2 `parties-membership-lifecycle` seam-notes (now landed); added the **demand-side suspension events payload-contract** section (8 payloads, naming traps, the Coverage-recompute coupling term, deferred seams, §4.1/§4.2.1/§4.7/§10.1/§15 anchors).

## Build & Quality Status
- Stack unchanged: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **949/949 green on SQLite** (945 baseline + 4 new chain tests) via `php -d memory_limit=512M vendor/bin/pest` (artisan test OOMs at 128M). PHPStan 0, Pint clean, `openspec validate parties-membership-suspension --strict` valid, `composer.json/lock` untouched.
- **Verified on PG17** (docker `postgres:17` :55432): **full suite 949/949** (103s — all modules + arch tests, the strongest close) + an explicit guard-file + chain-test audit **46/46**. The 29-event multiset, causation split, and audit-only zero-event counts all hold on PG (by-name/count; `causation_id` via `(int)` cast — traps 3 & 6).

## Active Change & Next Task
- **`parties-membership-suspension` — COMPLETE on `ralph/parties-membership-suspension` (11/11 tasks).** Emit `<promise>CHANGE_COMPLETE</promise>`. Awaiting human review/merge + `openspec archive` (humans do that, not ralph).
- **Next (a human, not ralph):** review the branch → merge → semantic-verify → `openspec archive parties-membership-suspension --yes`. Then pick the next change. Deferred seams the spec keeps open: **`parties-hero-package`** (Hero cap + `Applied → WaitingList`, after Module A), **`parties-customer-segments`**, and the cross-module consumers (Module E `MembershipFeePaid` listener, Module S AC-K-EVT-14 cancellation signal + Club-Credit conversion/freeze, `parties-anonymisation`).

## Blockers & Decisions Needed
- None. The change is green end-to-end on both engines; every acceptance bullet of all 11 tasks re-verified.
- Still open (human's call, pre-existing): **push `main` → `origin`** + delete the merged `ralph/parties-membership-activation` branch.

## Open Patterns
- **Full-slice integration chain test = the `Membership*ChainTest` template** (consolidated in progress.md): one file, a shared builder fn + per-concern `it()`s; the EXACT phase-grouped `toEqualCanonicalizing` multiset is the "no stray event" guard; one subject per divergent path + customer-cascade ops ordered after the per-subject journeys keep counts deterministic; root/child causation via `whereNotNull`/`whereNull` + `(int)` cast. **Docblock trap:** a literal `*/` in narrative prose (`Create*/Approve*`) closes the comment early → ParseError; write `Create / Approve`. Always run the new file ALONE once before the suite masks a parse error.
- All four prior coupling/cascade patterns (Hold→status on PLACE/LIFT, the cascade-causation-child template, coverage-recompute-under-lock) remain valid and are now exercised together by the chain test.
