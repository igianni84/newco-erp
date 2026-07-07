---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-07
---

# Hot Cache

## Last Updated
**2026-07-07 — Batched change `parties-module-k-br-guards` AUTHORED (prep-only, `/spec-to-change`, fresh window) — awaiting Giovanni's review → APPROVED.** Bundles RM-19 + RM-20 + RM-21 + RM-22 + RM-23(partial), all Module K / `party-registry`. `openspec validate --strict` **green**; 4/4 artifacts complete; **no APPROVED, no code, no ralph** (git shows only the change dir). Artifacts: proposal + design (D1–D9 + 3 mini-ADR plan + grounded Risks) + delta specs (**party-registry 4 ADDED / 6 MODIFIED**, **operator-console 4 MODIFIED**) + 7 task-groups (~24 tasks). Grounded via 4 subagents (frozen spec, current code+blast-radius, truth-specs, LIVE canon `cmless/main @ 360df0b`) + interview (4 forks resolved). Prior op — 2026-07-06: RM-08 closed (merged/archived/pushed, §2.7 CLEAN).

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. No code changed this session (prep-only).
- Full suite **1975/1975** (10497 assn) green on **SQLite AND PG17 (301s)** · PHPStan max **0** · Pint clean · all 10 truth-specs validate (state as of RM-08 close).
- **PG17 recipe:** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (the `pg` container; NOT :5432). Full suite via `php -d memory_limit=-1 vendor/bin/pest` (artisan test OOMs at 128 MB).

## Active Change & Next Task
- **`parties-module-k-br-guards` on disk, prep-only, awaiting APPROVED** (`openspec/changes/parties-module-k-br-guards/`). Giovanni reviews → creates APPROVED → `./ralph.sh --change parties-module-k-br-guards <n>`.
- **Scope IN:** RM-19 (offboarding Profile-leg orchestration, audit-only — signal event stays §15.7 seam), RM-20 (ProducerAgreement cross-shape reject — INVERTS shipped "MAY both be active"), RM-21 (Club sunset blocks CreateProfile), RM-22 (settlement_cadence `{quarterly,monthly,semi-annual}`), RM-23/{Agreement-4, Club-6, Identity-6, Profile-5-Kside, Producer-5-interim-lock}. **3 mini-ADRs** (DEC-009/010/022).
- **Carved OUT:** J-15a → RM-05 (capacity, MVP-DEC-011); Producer-5 full re-arm + Profile-5 self-toggle → own/Consumer-Portal; DEC-013 (no AC) + DEC-021 (=RM-25/RM-01).

## Blockers & Decisions Needed
- **Awaiting Giovanni's review of the authored change.** If he wants a dedicated RM-19 signal EVENT now (vs the audit-only orchestration default, design D1), that adds a 4th mini-ADR + a *Demand-Side Status Events* delta.
- Repo sync: `origin/main` == local `main` @ `bfb8fc7`. No ralph branch.

## Open Patterns
- **Grounding corrects tracker premises:** RM-20 has no "producer-wide flag" (single nullable `club_id`; real gap = cross-shape activation, and truth-spec asserted the OPPOSITE of frozen BR-K-Agreement-1); RM-22 was FREE-TEXT not a dropdown. Trust the code grep over the tracker's description.
- **RM-23 DEC attribution was loose:** the 5 buildable criteria trace to DEC-009 + DEC-022 only; J-15a is capacity (DEC-011); DEC-013/021 produce none of the named 6. Scope to the CRITERIA, map each to its real DEC.
- **Batch blast radius (build):** RM-20 inverts `ProducerAgreementLifecycleTest` L157/L206 (coexistence→reject); Club-6 drops `invite_only` (29 refs/16 files); RM-22 seeder `annual`→`semi-annual`; Producer-5 lock = zero-behaviour (all Producer updates touch only status/kyc_status).
