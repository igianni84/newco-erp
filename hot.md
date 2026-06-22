---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-22
---

# Hot Cache

## Last Updated
**2026-06-22 (`operator-console-parties-holds` — §2.7 close DONE: merged to main + archived, locally).** The Holds slice is shipped. Full §2.7 ritual run interactively: (1) branch review clean (12 commits, no `spec/`/`composer`/migration touch); (2) **PG17 full suite 1442/1442 (7950 assn), exit 0** — production engine confirmed, exactly the SQLite baseline; (3) **semantic verify — 2 independent agents, NO CRITICAL**; (4) merged `--no-ff` into `main` (local), `openspec archive` merged the delta into `openspec/specs/operator-console` (+1 req, ~2 req), change → `archive/2026-06-22-operator-console-parties-holds`. **Push to origin/main is PENDING (classifier-gated — ask Giovanni); branch `ralph/operator-console-parties-holds` not yet deleted.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN: PG17 full suite 1442/1442 (7950 assn, exit 0) this session** (the merge gate); SQLite 1442/1442 from 6.2; phpstan max 0; pint clean; living `operator-console` spec valid post-archive (`openspec validate --type spec --strict`).
- Full suite + arch `--filter` OOM at PHP default 128 MB in result *parsing* (not a failure). Run pest with `php -d memory_limit=-1`.

## Active Change & Next Task
- **`operator-console-parties-holds` is ARCHIVED (2026-06-22).** `openspec list` empty — no active change.
- **Immediate human step: push** `main` (archive + merge commits) to origin, then `git branch -d ralph/operator-console-parties-holds` — both PENDING Giovanni's go (push gate).
- **Next slice (author via `/spec-to-change`):** the sibling `operator-console-parties-kyc-sanctions` (KYC writes `RequireKyc`/`RecordKycVerified`/`RecordKycRejected` + sanctions `RecordCustomerScreening`; its tests can assert the kyc-Hold auto-place/auto-lift against THIS slice's Holds table), or the deferred Account/Profile lifecycle slices.

## Blockers & Decisions Needed
- **Push gate** — origin/main push wanted but classifier-gated; awaiting Giovanni's confirm. Local merge + archive + commit are done.
- **Non-blocking follow-ups (semantic WARNINGs — accepted, not archive-blocking):** the profile-scope path is the soft spot — (a) empty `profile_id`/null Account → `(int)null=0` → benign invisible orphan Hold (minor D4 drift; fix = conditional `->required()` on `profile_id`); (b) no end-to-end profile-scope *place* test; (c) no direct `IllegalHoldLift::notActive` domain `toThrow` in this delta. Each closable by ~1 line in a future hardening slice.

## Open Patterns
- **The Holds slice's reusable patterns live in the archived change's `progress.md` `## Codebase Patterns`** (travelled to archive): non-relation per-row-action `TableWidget` footer-widget vehicle; `getFooterWidgets()` + explicit-`record` hosting on a `ViewRecord`; non-relation scope-set OR-query; bespoke multi-operand header action reusing `surfaceLifecycleOutcome`; heterogeneous closing-chain envelope; OperatorPanel→Parties boundary carve-out **"EXERCISED, never WIDENED"** (ADR 2026-06-21). Landmines: a hidden Filament record-action is unreachable server-side (reject = domain `toThrow`, not `action_failed`); per-row key off typed `$record->id` not `getKey()` (`cast.int`); state enums (`HoldStatus`) stay cast-only.
