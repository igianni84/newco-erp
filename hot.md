---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-07
---

# Hot Cache

## Last Updated
**2026-07-07 — `parties-module-k-br-guards` ralph loop RUNNING. Task 1.2 (mini-ADR MVP-DEC-010 / settlement-cadence closed set) DONE, committed — 2/23.** Wrote `decisions/2026-07-07-adopt-mvp-dec-010-settlement-cadence-closed-set.md` (RM-22) + `decisions/INDEX.md` row. Grounded read-only vs LIVE canon `cmless/main @ 360df0b`: MVP-DEC-010 `Register:135` + AC-K-BR-Agreement-2 `Acceptance:193` verbatim (closed to 3 — `quarterly`/`monthly`/`semi-annual`; server-side API+DB not UI-only; `annual`/sub-monthly out of set). Code reality cited from source (free-text `->string()->nullable()`; DemoSeeder `annual` row → `semi-annual` recorded as the sole data consequence). `openspec validate --strict` **valid**.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. **No code changed yet** (1.1 + 1.2 = doc-only ADRs + INDEX rows).
- Baseline (RM-08 close): full suite **1975/1975** green on SQLite AND PG17 · PHPStan max **0** · Pint clean.
- **PG17 recipe:** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (`pg` container; NOT :5432). Full suite via `php -d memory_limit=-1 vendor/bin/pest` (artisan test OOMs at 128 MB).

## Active Change & Next Task
- **`parties-module-k-br-guards` — 2/23 done. NEXT = task 1.3:** last mini-ADR `2026-07-07-adopt-mvp-dec-022-club-membership-governance.md` (Club-6 + Identity-6 + Profile-5 + Producer-5). LIVE canon MVP-DEC-022 (CML-89) + AC-K-BR-Club-6/Identity-6/Producer-5/Profile-5. **Record each criterion's build-vs-deferred split** (task 1.3 acceptance names them): Producer-5 = content-immutability lock while `active` (safety core), full re-arm deferred (RM-06/RM-14 precedent); Profile-5 = K-side inherit + operator-set, customer self-toggle deferred (Consumer Portal); Identity-6 null-DOB → block; Club-6 collapses `invite_only` + no-auto-approve.
- **Grounding recipe + register/acceptance line-map + collision-banner + canon-adoption-KIND taxonomy are in the change `progress.md` → Codebase Patterns.** 1.3 is a 4-criterion batch + a *mix of kinds* — reuse the taxonomy.
- **Scope after the 3 ADRs:** §2 schema/enums/exceptions (2.1 `SettlementCadence` enum+cast+CHECK regen) → §3 ProducerAgreement guards (3.1 RM-22 closed-set + DemoSeeder `annual→semi-annual` / 3.2 Agreement-4 / **3.3 RM-20 inverts shipped coexistence tests L157+L206**) → §4 Profile+Club (RM-21/Profile-5/Club-6-drops-`invite_only`/RM-19) → §5 Customer+Producer (Identity-6/Producer-5-lock) → §6 console+i18n → §7 close (human-gated).

## Blockers & Decisions Needed
- None. Change is APPROVED; branch `ralph/parties-module-k-br-guards`. `origin/main` == local `main` @ `bfb8fc7`.

## Open Patterns
- **Canon-adoption KIND (name it per ADR):** (a) erratum-of-omission = adds a missing BR (009/Agreement-4); (b) value-domain tightening = closes an open set on an existing BR (010/Agreement-2, bridges DEC-042); (c) behaviour inversion = flips a shipped rule (RM-20). (a)+(b) low blast radius (add a negative test); (c) inverts shipped tests. The bridged greenfield DEC being *present* in our spec is the tell it's (a)/(b) not (c).
- **Two orthogonal ProducerAgreement guards, never conflate:** Agreement-4 (3.2) = creation-time Club-active; RM-20 (3.3) = activation-time cross-shape mutual-exclusion. Different chokepoints/exceptions.
- **Trust code-grep over the tracker:** RM-22 was FREE-TEXT not a dropdown; RM-20 has no "producer-wide flag" (single nullable `club_id`); Club-6 drops `invite_only` (29 refs/16 files).
