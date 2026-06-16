---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-16
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-16 (ralph iter 3 — task 2.1 of `catalog-lifecycle-approval` DONE).** Shipped the lifecycle FSM's single rejection exception + its localized copy (first lifecycle-mechanism task; no DB). `App\Modules\Catalog\Exceptions\IllegalLifecycleTransition extends RuntimeException` — 4 named factories `cannotSubmit/cannotActivate/cannotRetire/cannotReopen(LifecycleState $from, string $entity)` via a private `build()` helper (DRY; one place for the `(string) __()` coercion + `{state,entity}` placeholders). ONE parameterized class for all seven entities (uniform FSM → entity is a param; design D2). Copy in a new `lifecycle` group of the existing `lang/en/catalog.php` (`:state`/`:entity`, neither PII; valid-from per the D2 map). Cites Module K's `IllegalProducerTransition` analogue in PROSE — never `{@see \FQCN}` (auto-import would breach invariant 10).

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PG17.
- **Full suite 496/496 on SQLite** (was 487; +9), phpstan max 0, pint clean. **No PG run this task** (pure exception + i18n, touches no DB — last PG gate was 83/83 at task 1.2). `openspec validate catalog-lifecycle-approval --strict` green. Guards: no new migration, no composer drift, no protected files.

## Active Change & Next Task
- **`catalog-lifecycle-approval` — 3/17 tasks.** Module 0 lifecycle: 4-state FSM across 7 spine entities + Creator→Reviewer→Approver governance + Producer activation gate + first cross-module consumer (done 1.2) + cascades + 14 `*Activated`/`*Retired` events.
- **Next: task 2.2** — shared `LifecycleTransition` mechanism under `app/Modules/Catalog/Lifecycle/` (DB::transaction + `lockForUpdate` re-read + from-state assert → throws 2.1's `IllegalLifecycleTransition`; in-place `lifecycle_state` write; an `AuditRecorder` row per step) + Product Master `SubmitProductMasterForReview` (`draft→reviewed`) & `ReopenProductMaster` (`retired→reviewed`) — **audit-only, NO domain event**. First DB-touching lifecycle task → **PG17 run REQUIRED**. Verify `AuditRecorder::record(action,module,actorRole,actorId,entityType,entityId,before,after,authorizationBasis,…)` + `ActorContext::role()/actorId()` before use. Test: `tests/Feature/Modules/Catalog/ProductMasterLifecycleTest.php` (`DatabaseMigrations`, `actingAs(Operator::factory()->create(),'operator')`).

## Blockers & Decisions Needed
- **None blocking.** Three design judgment calls still standing for accept/veto during ralph: (1) KYC conjunct enforced upstream — gate is producer-`active` only (D6); (2) producer-state projection in design D3, not a standalone ADR (promotable on request); (3) separation-of-duties is audit-derived, no governance columns (D5).
- Deferred follow-ons (named): `catalog-operator-console` (Filament approval-queue UI), `parties-compliance` (KYC model + tightens `ActivateProducer`), Phase-3 referencers (cross-module retirement-blocking refs).

## Open Patterns
- **Full suite = `php -d memory_limit=512M vendor/bin/pest`** (NOT `php artisan test` — 128M OOMs in arch plugin; pao stdout fatal on PG).
- **One parameterized transition exception** when the FSM is uniform across entities (entity = factory param), vs a class-per-FSM when the machines genuinely differ (Module K). Localized via `(string) __('catalog.lifecycle.<key>', ['state'=>$from->value,'entity'=>$entity])`; `(string)` coerces the Larastan-`mixed` translator return.
- **Interpolation-proof i18n test:** assert the resolved message contains a token ABSENT from the key's literal template (a from-state ≠ the copy's valid-from), so its presence proves `:placeholder` interpolation, not coincidence. Cross-key datatest → synthetic sentinels. Template: `tests/Unit/Modules/Parties/Exceptions/TransitionExceptionsTest.php`. Add a "preserves existing groups" guard whenever appending keys to a shared lang file.
- **Pint `{@see \FQCN}` auto-import trap (sharpened):** auto-imports docblock FQCN refs → breaks PHPStan on not-yet-existent classes AND breaks `ModuleBoundariesTest` (invariant 10) on existing OTHER-module classes. Cite future/other-module classes in prose/backticks; same-namespace `{@see ClassName}` is safe.
- **PG17 gate** (for the next, DB-touching task): `docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17`; bounded `docker exec pg pg_isready -q` loop (in-container sleep); env prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco …`; `docker rm -f pg`. Traps in `knowledge/testing/rules.md`.
- **GUIDE §2.7 close** = verify both engines → merge `--no-ff` → semantic-verify → `openspec archive --yes` + commit → push (human-gated). APPROVED = human-only; never `git push` without explicit human OK.
