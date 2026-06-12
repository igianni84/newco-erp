---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-12
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-12 (interactive close session — `foundations-domain-events-audit` MERGED + ARCHIVED, both CI lanes green).** Giovanni delegated the GUIDE §2.7 close; done verify-first. Sequence: pre-merge gates green → `--no-ff` merge (54a057d) + push → semantic verify (2 subagents) = **MERGE-CLEAN, 0 CRITICAL** (1 WARNING: queued-gate is compile-time vs the scenario's literal runtime-rejection wording — deliberate D4, satisfies intent) → **first real `tests-pgsql` lane run RED (12 fail on PG 17)** → diagnosed: **0 production/migration/trigger defects**, all 12 were non-portable TEST assumptions → **6 test-only fixes** (5 edited + 2 new `InertConsumerA/B`) re-verified locally on **PostgreSQL 17.10 (151/151) AND SQLite (151/151)** → push (8629d41) → **CI green both lanes** (run 27433178902) → `openspec archive` → branch deleted. Engine-portability lesson captured in new `knowledge/testing/`.

## Build & Quality Status
- Stack unchanged: PHP 8.5.2 · Laravel 13.x · Filament v5 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev; tests `:memory:`. Prod engine PostgreSQL 17 (ADR).
- `main` @ 8629d41: full suite **151/151** — SQLite 602 assertions, **PostgreSQL 17.10 601** (the 1-assertion delta is the sqlite-only `:memory:` check) · phpstan 0 @ max · pint clean · CI run 27433178902 **quality ✅ + tests-pgsql ✅**. composer.json/lock unchanged vs the change baseline (zero new deps).
- The pgsql lane (D8) is now **proven green for real** — the PG branches of migrations (actor_role CHECK, partial pending index, plpgsql immutability triggers) execute and pass on PostgreSQL.

## Active Change & Next Task
- **NO active change.** `foundations-domain-events-audit` is merged, archived (`openspec/changes/archive/2026-06-12-foundations-domain-events-audit`), and its delta specs are now living truth: `openspec/specs/event-substrate/spec.md` (new, 8 requirements) + `openspec/specs/platform/spec.md` (updated). **Phase 1 (domain-events + audit substrate) CLOSED.**
- **Next:** `/spec-to-change` from `spec/05-release/Build_Workplan_v0.3-MVP.md` for **F2+** — the first real module that emits real spec events on the substrate. Queue-driver ADR gate expected around F4–F6 (first `queued` consumer); the substrate is launch-ready for it (inline is the only registrable `DeliveryMode` until then).

## Blockers & Decisions Needed
- **No blockers.** Two non-blocking carry-overs surfaced at close:
  1. **Queued-gate WARNING** — the archived `event-substrate` spec's "Queued mode is gated" scenario describes a *runtime* rejection citing the queue ADR; the code gates `queued` at *compile-time* (single-case enum). Deliberate (D4), stronger, satisfies intent — but living-spec text and impl differ. Decide later whether to reconcile the scenario wording (tiny future change) or leave as-is.
  2. **CI actions deprecation** — `.github/workflows/ci.yml` uses `actions/checkout@v4` + `actions/cache@v4` (Node 20; GitHub forces Node 24 from 2026-06-16). Bump to @v5 in a future change.
- Open ADR gates (unchanged): identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). Semantic-verify debts from bootstrap (W1/W2/W3/S1/S3) still pending Module K.

## Open Patterns
- **Closing ritual now includes a LOCAL PostgreSQL verify** before declaring done — the ralph loop can't run the pgsql lane, so SQLite-green ≠ done. `docker run postgres:17` on a spare port + `DB_CONNECTION=pgsql … php artisan test`. Full rule + the 5 PG-vs-SQLite traps in **`knowledge/testing/rules.md`** (NUL-truncated anonymous FQCNs; strict `uuid`; `jsonb` key-reorder; `timestamptz` `+00`; trigger-RAISE aborts the whole PG txn → savepoint-wrap).
- **Substrate (`App\Platform`)**: boundary law (never import `App\Modules\**`, arch-test enforced); recorder rides the caller's transaction (`NotInTransactionException` at level 0); envelope = UUIDv7 + money minor-units + FX decimal-string (never float, D18); delivery inline post-commit + `events:sweep` at-least-once (backoff/dead-letter, `config/events.php`); immutability via DB triggers (SQLite/PG parity, token `immutable`; audit redaction-only). Module identities cross the boundary as `string` (`Module::X->value`), never the enum.
