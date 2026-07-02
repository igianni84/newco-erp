---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-01 (`parties-anonymisation`) CLOSED via the full GUIDE §2.7 ritual ✅ — merged, semantic-verified, archived (all local).** The four legs ran clean: (1) **branch review** — 13 commits, 42 files, +2919/−22; (2) **`git merge --no-ff ralph/parties-anonymisation`** onto `main` → merge commit `2385772`; (3) **semantic verification** by 4 parallel subagents (anonymisation core, 6 scenarios · Hold precedence, 3 + canon · Address+Export+schema, 5 · console+i18n+completeness) → **0 CRITICAL, 0 WARNING, 4 low-impact SUGGESTIONs** only (each either already deferred/documented in design/ADR, or a latent forward-looking note) → gate GREEN; (4) **`openspec archive parties-anonymisation --yes`** → change moved to `openspec/changes/archive/2026-07-02-parties-anonymisation/`; `party-registry` truth spec absorbed **+4 ADDED / ~1 MODIFIED** requirements (Customer Anonymisation (Right-to-Erasure), Anonymisation Hold Precedence, Customer Address, Customer Data Export; Customer Suspension and Closure now cross-references anonymisation). Archive committed as `1109392` (structural-only, per convention). `openspec list` = No active changes.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- Last green (loop 7.1, pre-merge): full suite **1883/1883** (10189 assertions) on SQLite AND PG17; PHPStan max 0; Pint clean. Merge was a clean ort (no conflicts) and no code changed after it, so the green run still holds.
- `main` is **ahead of `origin/main`** by the merge `2385772` + archive `1109392` (+ this memory commit) — all **local/unpushed**.

## Active Change & Next Task
- **No active change.** RM-01 fully closed.
- **NEXT (gated): push `main` to origin** — the close-ritual push is classifier-gated; local steps are done, awaiting Giovanni's go. After push: **`/spec-to-change`** for the next slice per the Build Workplan.

## Blockers & Decisions Needed
- **None blocking.** Only open call = whether/when to push origin (human).
- **Incidental (candidate future change):** `party-registry` truth-spec *Hold Registry* still says "six-value" while code is **8** (RM-04 debt). RM-01's gate is `compliance`-only / count-independent → unaffected. Still open.

## Open Patterns
- **§2.7 human close = review → merge --no-ff (local) → parallel semantic-verify → archive → structural commit → (gated) push.** Archive commit is structural-only (openspec files); memory (hot/log) rides a separate commit. Verify by parallel subagents, one per requirement-cluster, judging assertion-coverage (suite already green), not test-pass. Clean gate = 0 CRITICAL/0 WARNING; good SUGGESTIONs → knowledge/ or a future change.
- **Latent audit-redaction convention (RM-01 SUGGESTION, uncaptured):** `AuditRecorder::redactEntity` matches only `entity_type='Customer'` + id and returns 0 silently on a miss. When the first Parties audit-writer / HubSpot seam lands, PII written under any other entity_type or a child-entity row (Address/Profile) won't be redacted and fails silent — pin the convention then.
- **Anonymisation gate = `compliance`-only, count-independent:** key on `HoldType::Compliance` via `PartyComplianceStatusReader`; never the `Hold` model; never wire `sanctions_status`.
