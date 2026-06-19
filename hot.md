---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-19
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-19 (interactive close-out — `parties-membership-activation` MERGED + ARCHIVED).** Ralph finished 7/7; this session ran the GUIDE §2.7 close: pre-merge quality gate (Pint clean, PHPStan 0, **836/836 SQLite + 836/836 PG17** cross-engine), `git merge --no-ff` → `main` (`4a27c61`), adversarial semantic-verify subagent → **CLEAN** (0 CRITICAL, 0 WARNING, 2 no-action SUGGESTIONs), then `openspec archive … --yes` → archived `2026-06-19-parties-membership-activation`, deltas merged into `openspec/specs/party-registry/spec.md` (+4 req, ~2 modified). **Local-only — not pushed** (human's call); branch `ralph/parties-membership-activation` still present (merged into main, safely deletable).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green 836/836** (3977 assertions) on SQLite AND PG17 (docker `postgres:17`, port 55432). phpstan 0; pint clean. No new dependency (`composer.json/lock` unchanged vs pre-merge `main`).
- On `main` at `4a27c61`. Working tree carries the `archive:` commit (archive moves + living-spec update + this memory refresh).

## Active Change & Next Task
- **No active change** (`openspec list` → none). `parties-membership-activation` shipped end-to-end + archived: composite onboarding gate (`ActivateCustomer`), audit-only `ApproveProfile`/`DeclineProfile` + Originating-Club one-shot lock, `ActivateProfile`, 3 root activation events, 2 `Illegal*Transition` exceptions, 1 additive migration (3 acceptance timestamps).
- **Next: HUMAN picks the next change.** Named follow-on seams (NOT started): `parties-membership-suspension` (Hold→`suspended`, `Active→Suspended|Lapsed|Cancelled|Inactive`, Account-status transitions); `parties-hero-package` (§13 capacity invariant + `Applied→WaitingList`, after Module A); `parties-customer-segments`; `club-credit`; `parties-operator-console` / producer portal.

## Blockers & Decisions Needed
- **Open (awaiting human go-ahead): push `main` → `origin` + delete the merged `ralph/parties-membership-activation` branch.** Close-out stayed local per the human's explicit step list (no `git push`).
- Deferred concerns stay deferred as named seams: §13 Hero-Package capacity → Module A; `MembershipFeePaid` listener + Club Credit → Module E; `OriginatingClubLocked` consumers (Module S settlement / E D19 accrual / HubSpot) → S/E. The 3 acceptance columns still have no production setter (deferred registration surface).

## Open Patterns
- **PG17 cross-engine close recipe** (knowledge/testing/rules.md): `docker run -d --name pg … -p 55432:5432 postgres:17` → wait `pg_isready` → `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 … php -d memory_limit=-1 vendor/bin/pest` → `docker rm -f pg`. Run pest **directly** (not `artisan test`: the pao stdout-teardown fatal swallows the JSON summary on PG); raise `memory_limit` (full suite OOMs at the 128M homebrew default).
- **Close ritual (GUIDE §2.7):** pre-merge gate green on BOTH engines (SQLite necessary, not sufficient); merge `--no-ff`; semantic-verify via adversarial subagent (CRITICAL→add tasks + re-loop; CLEAN→archive); archive fuses deltas into the living spec.
- **`originating_club_id` reads use loose `toEqual`** (uncast bigint FK — trap 6); event-payload ids stay `toBe` (jsonb ints, trap 3).
