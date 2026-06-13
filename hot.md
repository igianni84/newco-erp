---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-13
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-13 (interactive — 360° audit + second-brain source fix).** Full read-only audit (5 parallel agents: OpenSpec/traceability, substrate code, tests/CI, second brain, workplan coverage) + quality gates. Second brain now self-enforces: new `scripts/memlog.sh` (real-clock timestamp + 280-char outcome cap) and `.claude/hooks/memory-health.sh` (Stop hook: warns on hot.md >550w / log.md >200KB / giant entry lines); rules updated in `.claude/CLAUDE.md` + `RALPH.md`; `log.md` rotated to `log-archive-2026-H1.md`, restarted slim. Minor doc fixes (removed the non-existent `/opsx:verify`, PHP floor text → 8.5, grill ADR-format override).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.x (^13.8) · Filament v5 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev (`:memory:` tests); prod PostgreSQL 17.
- `main`: suite **151/151** green (SQLite 602 assertions; PG 17 lane green) · phpstan 0 @ max · pint clean · `composer validate --strict` + `composer audit` clean. The second-brain fix touched no app/test code.

## Active Change & Next Task
- **NO active change.** Phase-1 substrate (domain-events + audit) merged/archived.
- **CORRECTED Next:** F1 3/3 **`foundations-money-i18n-flags`** was wrongly skipped — author via `/spec-to-change` (Money value objects, i18n 6 locales D2, Pennant flags D12, actor_role helper). THEN F2 = Module 0 + K, with the identity/auth ADR grilled before any K slice.
- **Staged:** a `substrate-hardening` change (audit fixes below) — run via ralph after APPROVED.

## Blockers & Decisions Needed
- **Audit fixes (→ `substrate-hardening`):** executor inline-vs-sweep race (no row lock, `InlineDeliveryExecutor`); sweep `withoutOverlapping()` 24h TTL; silent dead-letters; CI no concurrency group; composer `php ^8.3`→`^8.5`; 4 test gaps (UUIDv7 pin, backoff cap, PG actor_role CHECK, mixed structural+redaction UPDATE).
- **Open ADR gates:** identity/auth (first to fire — before Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack (Module S).
- **Carry-overs:** queued-mode scenario wording (runtime) vs compile-time gate; bootstrap W1/W2/W3/S1/S3 (W2 seeder backdoor → fix with auth ADR). `openspec/specs` wording (Purpose TBD ×3, citations) rides future changes — no hand-edit.

## Open Patterns
- **Second brain self-enforces now:** append to log.md ONLY via `scripts/memlog.sh`; timestamps from the real clock; rotation by size (~200KB) not line count; `memory-health.sh` warns at Stop.
- **Closing ritual includes a LOCAL PostgreSQL verify** (SQLite-green ≠ done): `docker run postgres:17` + `DB_CONNECTION=pgsql php artisan test`. Traps in `knowledge/testing/rules.md`.
- **Substrate (`App\Platform`)**: boundary law (arch-test enforced); recorder rides caller's transaction; envelope UUIDv7 + minor-units + FX decimal-string; inline post-commit + `events:sweep` at-least-once; immutability via DB triggers (SQLite/PG parity); module identities cross as `string`.
