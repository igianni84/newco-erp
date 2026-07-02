---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-02
---

# Hot Cache

## Last Updated
**2026-07-02 — RM-01 (`parties-anonymisation`) CLOSED & PUSHED ✅ — full GUIDE §2.7 ritual, end-to-end.** Legs: (1) branch review (13 commits, 42 files, +2919/−22); (2) `git merge --no-ff` → `2385772`; (3) 4-way parallel semantic-verify → **0 CRITICAL, 0 WARNING, 4 low-impact SUGGESTIONs** → gate GREEN; (4) `openspec archive` → `party-registry` truth spec **+4 ADDED / ~1 MODIFIED** (Customer Anonymisation (Right-to-Erasure), Anonymisation Hold Precedence, Customer Address, Customer Data Export; Customer Suspension and Closure now cross-references anonymisation); change → `archive/2026-07-02-parties-anonymisation/`. Commits `1109392` (archive, structural-only) + `9f0ac46` (memory) **pushed to origin/main**; merged branch deleted. `openspec list` = No active changes.

## Build & Quality Status
- Stack unchanged: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- Last green (loop 7.1): full suite **1883/1883** (10189 assertions) on SQLite AND PG17; PHPStan max 0; Pint clean.
- `main` == `origin/main` (`9f0ac46`); working tree clean; nothing pending.

## Active Change & Next Task
- **No active change.** RM-01 done.
- **NEXT: `/spec-to-change` for RM-02 — Enhanced-KYC €10k / €50k threshold + review-queue** (only remaining P0 compliance-floor item; Remediation_Tracker Round-2 seq RM-01→**RM-02**→RM-03→RM-05; **no ADR gate**, size M). Scope: single-tx €10k + cumulative-annual €50k detection (order-completion check + periodic job) → set `enhanced_kyc_flag`/`enhanced_kyc_at`, create a Compliance review-queue entry, fire an AML-threshold lightweight re-screen (trigger-source `aml-threshold`). Sources: `spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md` **AC-K-J-7a** + **AC-K-EVT-12a**; `spec/02-prd/Module_K_PRD_v0.3-MVP.md` §4.1/§9.1 (DEC-035) + §9.2 (DEC-030). **Seam:** the order-completion trigger reads a Module-S (Commerce, still stub) value → build the periodic-job path K-side now, emit/consume a seam for the order path (as RM-01 did for Module-E Hold consumers). Today `enhanced_kyc_*` columns exist (seam only); detection/review-queue/event deferred in `RecordCustomerScreening.php`.

## Blockers & Decisions Needed
- **None blocking.**
- **Incidental (candidate future change, NOT next):** `party-registry` truth-spec *Hold Registry* still says "six-value" while code is **8** (RM-04 debt, tracker F4). Count-independent, non-blocking.

## Open Patterns
- **§2.7 human close = review → merge --no-ff (local) → parallel semantic-verify → archive → structural archive-commit + separate memory-commit → push → branch -d.** Verify by parallel subagents, one per requirement-cluster, judging assertion-coverage (suite already green), not test-pass. Clean gate = 0 CRITICAL/0 WARNING; good SUGGESTIONs → knowledge/ or a future change.
- **Latent audit-redaction convention (RM-01 SUGGESTION, uncaptured):** `AuditRecorder::redactEntity` matches only `entity_type='Customer'` + id, returns 0 silently on a miss. When the first Parties audit-writer / HubSpot seam lands, PII under another entity_type or a child row (Address/Profile) won't be redacted and fails silent — pin it then.
- **Compliance gate pattern (reusable for RM-02):** key on `HoldType::Compliance` via `PartyComplianceStatusReader`; never the `Hold` model; never wire `sanctions_status`.
