---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 (interactive close — `parties-core` MERGED + ARCHIVED).** GUIDE §2.7 ritual run end-to-end: re-verified every quality gate myself (pint --test, phpstan max **0**, full **428/1649 on SQLite AND PostgreSQL 17**, `openspec validate --strict`), then a 3-lens semantic-verify (completeness / adversarial correctness / coherence) → **0 CRITICAL**, 2 non-blocking WARNINGs (both consistent with the documented design). Merged `--no-ff` → `a51634f`, pushed, deleted branch `ralph/parties-core`; `openspec archive parties-core --yes` → `6db57aa` (10 requirements synced into `openspec/specs/party-registry/spec.md`). No active changes remain.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- **On `main` @ `6db57aa`** (pushed to origin/main). Last verified green: full **428/1649 ✅ SQLite AND ✅ PostgreSQL 17** (`php -d memory_limit=512M vendor/bin/pest`), phpstan max **0** ✅, pint --test ✅. `openspec list` → no active changes.

## Active Change & Next Task
- **NONE active.** The `party-registry` capability spine is live in `openspec/specs/`. Catalog + Parties spines both landed (Phase-2 foundations).
- **Next (HUMAN choice via `/spec-to-change`):** scope a new slice. Natural follow-ons = the deferred Parties slices named in the archived design — most natural **`parties-membership-lifecycle`** (approve/decline write, Profile activation, the OC one-shot `OriginatingClubLocked` lock, `*Activated`/lifecycle events), then `parties-holds`, `parties-compliance`, `club-credit`, `parties-gdpr-retention`, `parties-operator-console`. Or advance the workplan (Phase 3 commercial primitives: Allocation + Procurement).

## Blockers & Decisions Needed
- **None blocking.** Carry-forward note for the lifecycle slice (semantic-verify WARNING): Parties models use `$guarded = []`, so immutability of `party_type` / `producer_id` / `originating_club_id` rests on the "Action is the sole writer" convention + DB structure (no `$fillable` whitelist / DB trigger). When the lifecycle slice adds writers, keep (or harden) that discipline so the immutable fields stay immutable.

## Open Patterns
- **§2.7 close ritual = re-run every gate yourself (never trust the loop's claims) + a perspective-diverse semantic-verify (completeness / adversarial correctness / coherence), then merge `--no-ff` → push → `branch -d` → `openspec archive` → commit → memory close.** Precedent commit trio: `merge: X` → `archive: X` → `chore: memory close (hot.md + log.md) — X merged+archived`.
- **Cross-engine close = `php -d memory_limit=512M vendor/bin/pest` on BOTH engines** (Arch OOM is a runner artifact). PG17 via `docker run postgres:17` on port 55432 + env override; full 428 on PG ~26s.
- Spine DB-entity template, partial-unique index, co-provisioning, two-FK idiom, party-type marker (no default), PII discipline (payload + rejection copy), driver-guarded enum CHECK from `Enum::cases()`, PG17 gate every DB task — proven across catalog + parties spines. `lang/{locale}/parties.php` = shared rejection copy. **log.md via `memlog.sh` only; hot.md ≤550 words; APPROVED = human-only.**
