---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-07
---

# Hot Cache

## Last Updated
**2026-07-07 — `parties-module-k-br-guards` ralph loop RUNNING. Task 1.3 (mini-ADR MVP-DEC-022 / Club-6 + Identity-6 + Profile-5 + Producer-5) DONE, committed — 3/23. All three canon mini-ADRs complete.** Wrote `decisions/2026-07-07-adopt-mvp-dec-022-club-membership-governance.md` (RM-23 batch, CML-89) + `decisions/INDEX.md` row. Grounded read-only vs LIVE canon `cmless/main @ 360df0b`: MVP-DEC-022 `Register:147` + AC-K-BR Club-6 `:175` / Identity-6 `:146` / Profile-5 `:164` / Producer-5 `:185` verbatim. One BUILD/DEFER pair per criterion, each kind-tagged. Frozen-spec gap confirmed (Club-1..5/Identity-1..5/Producer-1..4/Profile-1..4 only; bridged DEC-069/073 present). `openspec validate --strict` **valid**.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. **No code changed yet** (1.1+1.2+1.3 = doc-only ADRs + INDEX rows). Task 2.1 is the FIRST PHP task — the full Quality Loop (format→test→phpstan→pint) applies from there.
- Baseline (RM-08 close): full suite **1975/1975** green on SQLite AND PG17 · PHPStan max **0** · Pint clean.
- **PG17 recipe:** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (`pg` container; NOT :5432). Full suite via `php -d memory_limit=-1 vendor/bin/pest` (artisan test OOMs at 128 MB).

## Active Change & Next Task
- **`parties-module-k-br-guards` — 3/23 done. NEXT = task 2.1:** `App\Modules\Parties\Enums\SettlementCadence` (`Quarterly`/`Monthly`/`SemiAnnual` → `quarterly`/`monthly`/`semi_annual`) + cast `ProducerAgreement.settlement_cadence` + migration regenerates the `parties_producer_agreements` `settlement_cadence` CHECK from `cases()` (mirror the `parties_holds`/`HoldType` idiom; Postgres-truthful, SQLite skips CHECK — cast is the floor). Acceptance: `SettlementCadenceEnumsTest` (`toHaveCount(3)`, 3 values, default `quarterly`; PG CHECK = 3 tokens).
- **Grounding recipe + register/AC line-map + collision-banner + canon-adoption-KIND taxonomy + multi-criterion BUILD/DEFER rule are in the change `progress.md` → Codebase Patterns.** From 2.1 on it's real code — grep callers before each guard (RM-08 landmine: console `callAction` callers are invisible to the Action grep).
- **Scope after §1:** §2 (2.1 enum / 2.2 `auto_renew`+`auto_renew_default` cols / 2.3 drop `invite_only` / 2.4 five localized exceptions) → §3 ProducerAgreement (3.1 RM-22 closed-set + DemoSeeder `annual→semi-annual` / 3.2 Agreement-4 / **3.3 RM-20 inverts shipped coexistence tests L157+L206**) → §4 Profile+Club (RM-21/Profile-5/Club-6-drops-`invite_only`/RM-19) → §5 Customer+Producer (Identity-6 null-DOB→block/Producer-5 interim lock) → §6 console+i18n → §7 close (human-gated).

## Blockers & Decisions Needed
- None. Change is APPROVED; branch `ralph/parties-module-k-br-guards`. `origin/main` == local `main` @ `bfb8fc7`.
- Note: a `cat >> progress.md` heredoc tripped the git-guardrails Bash hook (false-positive on spec-path strings in the body). **Append memory files via the Edit tool, not `cat >>` heredocs.**

## Open Patterns
- **Canon-adoption KIND (name it per ADR/criterion):** (a) erratum-of-omission = adds a missing BR (009/Agreement-4; Identity-6; Profile-5); (b) value-domain tightening = closes/collapses a set on an existing surface (010/Agreement-2; Club-6 field-collapse); (c) behaviour inversion = flips a shipped rule (RM-20). (a)+(b) low blast radius (negative test / column / sweep); (c) inverts shipped tests. A bridged greenfield DEC *present* in our frozen spec is the tell it's (a)/(b) not (c). MVP-DEC-022 is a mix; Producer-5 is a 4th shape (interim safety lock, stricter-than-canon, zero behaviour today).
- **Two orthogonal ProducerAgreement guards, never conflate:** Agreement-4 (3.2) = creation-time Club-active; RM-20 (3.3) = activation-time cross-shape mutual-exclusion. Different chokepoints/exceptions.
- **Trust code-grep over the tracker:** RM-22 was FREE-TEXT not a dropdown; RM-20 has no "producer-wide flag" (single nullable `club_id`); Club-6 drops `invite_only` (29 refs/16 files); Producer FSM has no `reviewed` state (Producer-5 re-arm is deferred).
