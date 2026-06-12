---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-12
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-12 19:55 (ralph iter — task 3.4/15 ✅ green: DomainEventRecorder)** — Gruppo 3 (substrate API) **COMPLETO 4/4**. NUOVO: `App\Platform\Events\DomainEventRecorder` — `record(name, module:string, ActorRole, ?actorId, entityType, entityId, payload, ?correlationId=null, ?causationId=null): DomainEvent`. Ctor-inietta il singleton `ConsumerRegistry` (`private readonly`; nessun binding esplicito per il recorder — il container auto-wire risolve il singleton). Guard `DB::transactionLevel()===0` → `NotInTransactionException::forRecording('a domain event')` (condivisa con 3.3). `$eventId = (string) Str::uuid7()` catturato UNA volta → `event_id` + default `?? $eventId` di `correlation_id` (root = la propria radice, l'UNICO punto dove i default dei due recorder differiscono). Envelope: `schema_version` 1 hardcoded, `occurred_at` `CarbonImmutable::now('UTC')`, `causation_id` nullable, `module` plain `string` (boundary D1). Fan-out: una riga `pending` `event_deliveries` per `consumersFor($name)` DOPO `DomainEvent::create()`, nella txn del chiamante. **NESSUN `DB::afterCommit`** (è 4.1). Test `tests/Feature/Platform/DomainEventRecorderTest.php` (DatabaseMigrations) 11/44.

## Build & Quality Status
- Stack invariato: PHP 8.5.2 · Laravel 13.x · Filament v5 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev; tests `:memory:`.
- Quality su `ralph/foundations-domain-events-audit`: full suite **125/125 (462 assertioni)** ✅ (era 114/418, +11 = DomainEventRecorderTest) · type_check 0 @ level max ✅ · lint ✅ · `openspec validate foundations-domain-events-audit --strict` ✅. `composer.json/lock` invariati vs main (zero nuove dep). Schema (gruppo 2) CHIUSO; substrate API (gruppo 3) **CHIUSO 4/4** (model 3.1 · contract/registry 3.2 · AuditRecorder 3.3 · DomainEventRecorder 3.4).

## Active Change & Next Task
- **Attivo: `foundations-domain-events-audit` — APPROVED, loop in corso. 9/15 task fatti.**
- **Prossimo task: 4.1** — `App\Platform\Events\InlineDeliveryExecutor` + wiring post-commit (design D5). `deliver(array $domainEventIds)`: carica le delivery `pending` due ordinate per `domain_event_id`, risolve ogni consumer dal container (`app($fqcn)`), handler + flip `done` (attempts+1) **dentro UNA txn DB per delivery**; su throw — rollback txn handler, registra il fallimento separatamente (attempts+1, `available_at = now + backoff(attempts)`, `last_error`; `failed` a max attempts), continua col next (isolamento R4). **AGGIUNGE l'hook `DB::afterCommit` a `DomainEventRecorder`** (UNO per txn, raccoglie gli id eventi recorded → li passa all'executor). **Landmine verify-in-vendor PRIMA**: semantica afterCommit di `Illuminate\Foundation\Testing\DatabaseTransactionsManager` sotto RefreshDatabase (design D5 ladder fallback — se diverge, chiama `deliver()` esplicitamente nei test + copri l'hook in un test mirato non-wrapped; la at-least-once non dipende dall'hook, è lo sweep). Test `tests/Feature/Platform/InlineDeliveryTest.php`: consumer invokabili che scrivono righe marker su `cache` + un consumer che lancia; ordine d'invocazione in uno static/array sink.
- Ordine successivo: 4.2 events:sweep + schedule + config/events.php · 5.1 events:demo + E2E pipeline · 5.2 lane CI pgsql · 6.1 docs/event-substrate.md · 6.2 sweep finale + mapping scenari→test.

## Blockers & Decisions Needed
- **Nessun blocker.**
- **DatabaseMigrations per i test dei recorder/executor** (3.3/3.4 stabilito, 4.1 idem dove serve livello 0): un guard `transactionLevel() === 0` è intestabile sotto RefreshDatabase (wrapper → livello ≥1); DatabaseTruncation è OUT (il DELETE per-tabella colpisce i trigger immutabilità append-only); DatabaseMigrations (migrate:fresh = DDL non-triggerato) lascia i test a livello 0 → scritture wrap in `DB::transaction()` esplicito, guard test bare. Bonus: `DB::transaction()` COMMITTA/rollbacka in modo OSSERVABILE (necessario per gli atomicity test di 3.4 — usati: `cache`-write come state change, design D9). MA 4.1 testa l'hook afterCommit, che potrebbe richiedere RefreshDatabase o un test non-wrapped — risolvere via la verifica vendor sopra.
- La lane CI pgsql (5.2) + i branch PG (CHECK 2.1/2.2, partial index 2.3, trigger 2.4) sono visti verdi solo al push umano pre-merge (il loop non pusha; parte dell'acceptance di review, design D8). Il codice 3.1–3.4 è engine-agnostic.
- Carry-over: gate ADR aperti (identity/auth K · queue driver F4–F6 · object storage INV1 · hosting EU F7 · frontend TanStack S); `openspec/specs/module-architecture/spec.md` ha `Purpose: TBD` (tidy opzionale). Debiti semantic-verify W1/W2/W3/S1/S3 dal bootstrap (gate Module K).

## Open Patterns
- **Recorder fan-out + singleton ctor-iniettato** (3.4 → Codebase Patterns): un servizio platform che serve la `ConsumerRegistry` la ctor-inietta `private readonly` (il container risolve il singleton AppServiceProvider — STESSA istanza dei provider/test); il servizio non serve binding. Fan-out: enum-cast columns prendono l'ISTANZA su write (`DeliveryStatus::Pending`). Il recorder NON apre txn propria e NON chiama `afterCommit` (è 4.1). I default `correlation_id` differiscono: audit→uuid7 fresco, evento→il proprio `event_id`.
- **Atomicity test = DatabaseMigrations + tabella `cache` come state change** (3.4): commit/rollback REALE solo a livello 0; `DB::table('cache')->insert(['key','value','expiration'])` è lo state change canonico (design D9). Commit: wrap state+record in `DB::transaction()`, assert 3 tabelle. Rollback: `expect(fn () => DB::transaction(fn () => {…; throw;}))->toThrow()` poi 3 tabelle vuote. Riusato da 5.1.
- **Due `new class` distinti ⇒ due FQCN anonime distinte** (3.4): N consumer distinti per un fan-out = N blocchi `new class` separati (uno stesso statement 2× = stesso nome-classe). La singleton registry è fresca per-test (no leak).
- **Platform parla `string` non l'enum `Module`** (boundary law, 3.3): ogni API platform che porta un'identità modulo la tipa `string` (`Module::X->value` o `'platform'`). I test possono importare `Module` (l'arch suite scansiona solo i sorgenti `App\Platform`). `NotInTransactionException` condivisa (`forRecording`).
- Convenzioni F1 restano legge: verify-vendor-first, runner JSON, per-file `uses(...)`, model platform conventions (3.1), `DB::statement` per DDL branch-ata (2.4), helper Pest GLOBALI → prefissa (`recordTestEvent`/`recordTestAudit`/`registerTwoFakeConsumers` unici).
