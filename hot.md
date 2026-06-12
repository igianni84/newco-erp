---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-12
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-12 (interactive — CI actions deprecation carry-over RESOLVED).** Bumped `.github/workflows/ci.yml` off the deprecated Node 20 runtime: `actions/checkout@v4 → @v6` and `actions/cache@v4 → @v5` on both lanes (Giovanni's standing preference: always latest stable). Verified on the tags' `action.yml`: checkout@v6 (latest stable v6.0.3) and cache@v5 both `runs.using: node24`; `shivammathur/setup-php@v2` was already node24 (left as-is). No app code touched — `CiWorkflowTest` stays green (8/8; it pins `actions/cache@` without a version and doesn't pin checkout). Committed + pushed to `origin/main`.
Prior session (now history, see log.md): `foundations-domain-events-audit` merged + archived, both CI lanes green after 6 test-only engine-portability fixes on PostgreSQL 17.10.

## Build & Quality Status
- Stack unchanged: PHP 8.5.2 · Laravel 13.x · Filament v5 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev; tests `:memory:`. Prod engine PostgreSQL 17 (ADR).
- `main`: CI-actions bump is the new HEAD on top of `c6df3dd`. **No production/test code changed** — full suite still **151/151** (SQLite 602 assertions, PostgreSQL 17.10 601; the 1-assertion delta is the sqlite-only `:memory:` check) · phpstan 0 @ max · pint clean. composer.json/lock unchanged (zero new deps).
- CI workflow now Node-24-clean on both lanes (checkout@v6, cache@v5, setup-php@v2). The pgsql lane (D8) remains proven green for real (last full run 27433178902); the next push re-runs both lanes against the bumped actions.

## Active Change & Next Task
- **NO active change.** `foundations-domain-events-audit` is merged, archived (`openspec/changes/archive/2026-06-12-foundations-domain-events-audit`), and its delta specs are now living truth: `openspec/specs/event-substrate/spec.md` (new, 8 requirements) + `openspec/specs/platform/spec.md` (updated). **Phase 1 (domain-events + audit substrate) CLOSED.**
- **Next:** `/spec-to-change` from `spec/05-release/Build_Workplan_v0.3-MVP.md` for **F2+** — the first real module that emits real spec events on the substrate. Queue-driver ADR gate expected around F4–F6 (first `queued` consumer); the substrate is launch-ready for it (inline is the only registrable `DeliveryMode` until then).

## Blockers & Decisions Needed
- **No blockers.** One non-blocking carry-over remains:
  1. **Queued-gate WARNING** — the archived `event-substrate` spec's "Queued mode is gated" scenario describes a *runtime* rejection citing the queue ADR; the code gates `queued` at *compile-time* (single-case enum). Deliberate (D4), stronger, satisfies intent — but living-spec text and impl differ. Decide later whether to reconcile the scenario wording (tiny future change) or leave as-is.
  - *(Resolved 2026-06-12: the CI-actions Node-20 deprecation carry-over — bumped to checkout@v6 + cache@v5.)*
- Open ADR gates (unchanged): identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S). Semantic-verify debts from bootstrap (W1/W2/W3/S1/S3) still pending Module K.

## Open Patterns
- **Closing ritual now includes a LOCAL PostgreSQL verify** before declaring done — the ralph loop can't run the pgsql lane, so SQLite-green ≠ done. `docker run postgres:17` on a spare port + `DB_CONNECTION=pgsql … php artisan test`. Full rule + the 5 PG-vs-SQLite traps in **`knowledge/testing/rules.md`** (NUL-truncated anonymous FQCNs; strict `uuid`; `jsonb` key-reorder; `timestamptz` `+00`; trigger-RAISE aborts the whole PG txn → savepoint-wrap).
- **Substrate (`App\Platform`)**: boundary law (never import `App\Modules\**`, arch-test enforced); recorder rides the caller's transaction (`NotInTransactionException` at level 0); envelope = UUIDv7 + money minor-units + FX decimal-string (never float, D18); delivery inline post-commit + `events:sweep` at-least-once (backoff/dead-letter, `config/events.php`); immutability via DB triggers (SQLite/PG parity, token `immutable`; audit redaction-only). Module identities cross the boundary as `string` (`Module::X->value`), never the enum.
