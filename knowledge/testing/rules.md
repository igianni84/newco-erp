# Testing — Rules (apply by default)

> Promoted from `hypotheses.md` (3 dated confirmations) or derived directly from a canonical decision / arch-test / CI finding. A contradiction demotes a rule back to `hypotheses.md`.

## A test is not done until it is green on BOTH SQLite and PostgreSQL — SQLite-green is necessary, never sufficient

**Rule.** Every test must pass on the SQLite dev/test lane AND on the PostgreSQL production engine (ADR `2026-06-12-production-db-engine`). The `tests-pgsql` CI lane (design D8) is the gate, but it only runs on a human push to the remote — the ralph loop never sees it, so a 100%-green SQLite suite can still hide PG-only failures. When a task's tests touch the DB, **verify against a real PostgreSQL 17 before declaring done**:

```bash
docker run -d --name pg -e POSTGRES_DB=newco_test -e POSTGRES_USER=newco -e POSTGRES_PASSWORD=newco -p 55432:5432 postgres:17
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php artisan test
docker rm -f pg
```

**Two runner artifacts on the PG full-suite run (not test failures) — seen once the suite passed ~400 tests (`parties-core` 4.1, 416 tests):**
- **`pest-plugin-arch` OOM.** The Arch tests parse every class's docblocks (`phpstan/phpdoc-parser`), which exhausts the **default 128 MB** CLI `memory_limit` on the heavier PG run (the code is engine-independent; PG's footprint just tips it over). Fatal: `Allowed memory size … exhausted in …/phpdoc-parser/…`. Fix: run the PG full suite with `php -d memory_limit=512M …`.
- **`laravel/pao` stdout-teardown fatal.** `php artisan test` on PG throws at shutdown — `Laravel\Pao\Execution->restoreStdout()` → `stream_filter_remove(...)` → `NoTestCaseObjectOnCallStackException` — which **swallows the JSON summary** (it does NOT happen on SQLite; PG emits NOTICE output that collides with pao's stdout filter). Fix: run **`vendor/bin/pest` directly** (bypasses the artisan/pao wrapper) for a clean `{"result":"passed",…}` line.

So the full cross-engine close at scale is `… php -d memory_limit=512M vendor/bin/pest`; for a focused per-task gate, `… php artisan test tests/Feature/Modules/<Module>` runs clean under both at default memory. Either way, **also prove the PG-only constraints directly** (`migrate:fresh` on PG → seed parents → attempt each forbidden insert via `docker exec pg psql -c …`, assert the named CHECK/FK/unique constraint rejects it) — the SQLite lane cannot enforce them, which is the gate's whole point.

The five recurring SQLite-vs-PG portability traps — assert around them from the start:

1. **No anonymous classes as PERSISTED identities.** A registered consumer FQCN (or any string a test writes into a column and reads back on PG) must be a NAMED class. An anonymous-class name is `class@anonymous\0<path>:<line>` — it carries a **NUL byte**; PostgreSQL `text`/`varchar` TRUNCATES a value at its first NUL, so two distinct anonymous instances collapse to the same stored string and collide on a unique index (a false `23505`). SQLite keeps the NUL, hiding the bug. Use named doubles under `Tests\Support\` (e.g. `InertConsumerA/B`). This SUPERSEDES the archived change's "two `new class` statements ⇒ two distinct FQCNs" pattern for anything PERSISTED — and is the production-faithful shape (real module consumers are always named).
2. **`uuid` columns are strict on PG.** Use real `Str::uuid()` values; never human-readable literals like `'evt-happy'` (PG: `22P02 invalid input syntax for type uuid`; SQLite varchar accepts anything). A test that means to prove "duplicate rejected" must insert a VALID uuid twice — otherwise PG throws on the first insert (uuid-invalid) and the test passes for the wrong reason.
3. **`jsonb` is normalized, not byte-text.** PG `jsonb` reorders keys and adds a space after each colon. Never byte-compare a raw jsonb value, and never `->toBe([...])` a whole payload array (order-sensitive). Assert by key (`$payload['fx_rate']`), or read through the model's `array` cast and compare the decoded array.
4. **`timestamptz` renders a zone suffix on PG.** An uncast timestamp reads back as `...+00` on PG, plain `Y-m-d H:i:s` on SQLite. Assert the prefix (`toStartWith`), or read through an `immutable_datetime` cast and `->format(...)`.
5. **A trigger `RAISE` aborts the WHOLE transaction on PG.** The pattern "attempt a forbidden write, catch the QueryException, then SELECT to confirm the row is unchanged" works on SQLite (statement-level abort) but on PG the follow-up SELECT hits `25P02 current transaction is aborted`. Wrap the forbidden DML in a nested `DB::transaction()` (a SAVEPOINT under the RefreshDatabase wrapper) so only the savepoint aborts and the verification query survives. Behaviour-only message assertions (`toContain('immutable')`, never SQLSTATEs) already span both engines — it is the verify-after-throw that needs the savepoint.

**Origin (dated).** 2026-06-12, `foundations-domain-events-audit` close: the suite was 151/151 green on SQLite but the first `tests-pgsql` lane run (at human merge) was RED with 12 failures spanning exactly these five traps. Zero production / migration / trigger defects — the substrate was correct on PG; all 12 were non-portable TEST assumptions. Fixed test-only (commit `8629d41`), re-verified 151/151 on PostgreSQL 17.10 locally, both CI lanes green.

**Applies to.** Every DB-touching test, especially every F2+ module's tests (the substrate's consumers / recorders / immutability checks set the template). Pairs with the Postgres-truthful migration idiom in the archived change's progress.md: the fluent column types fall back behavior-preservingly on SQLite (`jsonb()`→text, `uuid()`→varchar, `timestampTz()`→datetime text) — and that FALLBACK is exactly where the asymmetry hides.
