---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`operator-console-parties-kyc-sanctions` — §2.7 closure ritual DONE: reviewed → merged `--no-ff` → semantic-verified → archived).** The change is ARCHIVED (`openspec/changes/archive/2026-06-23-operator-console-parties-kyc-sanctions`); its delta is merged into the living `openspec/specs/operator-console/spec.md` (+2 req ADDED — Customer KYC console + sanctions-screening console; ~2 req MODIFIED — status verbs + read-only context). `openspec list` → **no active changes**. Semantic verify (2 independent subagents) returned **NO CRITICAL**: completeness COMPLETE (every normative scenario asserted, 13/13 tasks proven in merged code), correctness/coherence CLEAN (both KYC visibility predicates the exact complement of their domain guards, screening option-set the exact complement of the onboarding floor, `KycStatus` never imported, D1–D11 realized). Two accepted non-blocking WARNINGs (per-verb `!isConfirmationRequired()` not pinned; Req-4 read-only scenarios live in the prior customer slice).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (loop 5.x): full suite 1495/1495 (8263 assn, exit 0) on SQLite; PHPStan max 0 err; `pint --test` clean.** PG17 gate met IN-LOOP at task 4.2 (Parties OperatorPanel folder 372/372, 1854 assn) — NOT re-run post-merge (this change adds NO migration / schema / new query, so untouched tests can't newly fail on PG).
- Full suite OOMs at PHP default 128 MB in result parsing — run pest with `php -d memory_limit=-1`.

## Active Change & Next Task
- **NONE active — change ARCHIVED.** Module K Parties console now covers: status verbs (customer slice) + Holds place/lift (holds slice) + 3 KYC verbs + sanctions screening (this slice). All write-through domain Actions, no Eloquent write.
- **`main` is +16 commits ahead of `origin/main`, UNPUSHED** (14 ralph commits + `merge:` + `archive:` + the memory refresh). Local `ralph/operator-console-parties-kyc-sanctions` branch still present.
- **Next: pick the next slice from `spec/05-release/Build_Workplan_v0.3-MVP.md`** via `/spec-to-change`. Parties remaining workplan candidates: club+membership, club credit, GDPR/retention. Confirm the exact next cut against the workplan before proposing.

## Blockers & Decisions Needed
- **Push gate (close-ritual convention):** `git push` (origin/main) + `git branch -d ralph/operator-console-parties-kyc-sanctions` await Giovanni's explicit go (classifier-gated — ask, do local steps first). All local steps done.
- RESOLVED: the earlier "Holds push gate" — Holds is already on `origin/main` (verified 2026-06-23, branch already deleted). No backlog beyond this change's push.

## Open Patterns
- **§2.7 closure run interactively:** review diff → merge `--no-ff` → semantic-verify via 2 independent subagents (completeness vs correctness/coherence), classify CRITICAL/WARNING/SUGGESTION → archive only if no CRITICAL. The 2-agent split found nothing CRITICAL (the change was over-covered).
- **Verify-only / memory-only closing task** (loop 5.2 mirrors 4.2/5.1): the validation/sweep IS the deliverable; the full suite subsumes per-task `--filter` runs.
- **Recalled-memory staleness:** a hot.md / team-memory flag can lag reality — verify before acting (the "Holds push pending" note was already false).
