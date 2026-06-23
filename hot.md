---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`club-credit` CLOSED — merged + archived, local).** The GUIDE §2.7 close ritual ran end-to-end: cold branch review → real PG17 full-suite re-verify → `git merge --no-ff ralph/club-credit` into `main` → 3-agent semantic verification (all clean) → `openspec archive club-credit`. The delta (**5 added + 1 modified** requirement) is merged into the living spec `openspec/specs/party-registry/spec.md`; the change moved to `openspec/changes/archive/2026-06-23-club-credit/`. **`main` is ahead of `origin/main`, UNPUSHED** — push is classifier-gated, awaiting Giovanni's go.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN — real PG17 (close ritual, 2026-06-23):** full suite **1560/1560 (8500 assn)** against a throwaway `postgres:17` Docker container (`DB_CONNECTION=pgsql … php -d memory_limit=-1 vendor/bin/pest`, ~190s). The raw partial index `CREATE UNIQUE INDEX … WHERE state='active'` migrated cleanly on the production engine. SQLite green per 5.4; PHPStan max 0; Pint clean; `openspec validate --strict` valid.
- **Full suite: `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`** (laravel/pao OOMs at 128 MB; lessons.md 2026-06-20).
- **PG17 IS runnable locally via Docker** for the close ritual (GUIDE §2.7 `docker run … postgres:17` recipe; knowledge/testing/rules.md). The "no local PG" caveat only means `psql`/`pg_ctl` binaries are absent — the Docker daemon is up; use it at close rather than relying on by-construction + CI alone.

## Active Change & Next Task
- **No in-flight change.** `club-credit` is archived (`2026-06-23-club-credit`); `openspec list` shows no active change.
- **Immediate next step (human): push `main`** → CI both lanes (quality + tests-pgsql) green → then `git branch -d ralph/club-credit` (kept as the safety net until push lands).
- **After push:** the next slice is a human-driven `/spec-to-change` — Module K continuation or whatever `spec/05-release/Build_Workplan_v0.3-MVP.md` calls next. The loop picks up nothing until a new change has an `APPROVED` file + unchecked tasks.

## Blockers & Decisions Needed
- **No blocker.** The only pending action is the gated push (the merge + archive commits sit on local `main`, ahead of origin).
- Deferred SEAMS (NOT blockers; now in the LIVING spec): Module-E `MembershipFeePaid` listener + `ClubCredit*` consumers (F6); Module-S checkout redemption + DEC-110/111 mutual-exclusion + DEC-043 closure-conversion + order-cancellation restore; year-end forfeiture scheduler; Profile-cancellation forfeit cascade.

## Open Patterns
- **Close ritual = review → PG17 (Docker) → merge --no-ff → 3-agent semantic verify → archive → ask-before-push.** Verifiers split by requirement cluster returned only SUGGESTIONs + one WARNING (currency immutability holds structurally, untested) — nothing blocking.
- **The 4 writers (Issue/Apply/Forfeit/Restore) are in `SupplyLifecycleChainTest`'s `$clubCreditWriters` allow-list** — any new non-`Create` Parties Action MUST be added there or the exact-match `toEqualCanonicalizing` reds.
- **The freeze is enforced at the REDEMPTION site, not suspension:** `Suspend*` stays state-preserving; `ApplyClubCredit` reads the owning Profile's live `state`.
