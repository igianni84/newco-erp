---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-12
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-12 18:05 (ralph iter — task 3.1/15 ✅ green: model Eloquent, layer applicativo sopra lo schema)** — Aperto il **gruppo 3 (substrate API), 1/4**. TRE model nuovi sotto `App\Platform`: `Events\DomainEvent` (`$table='domain_events'`, `$timestamps=false`, casts `payload`→array/`occurred_at`→immutable_datetime/`actor_role`→`ActorRole::class`/`schema_version`→int), `Events\EventDelivery` (timestamps TENUTI, casts `status`→`DeliveryStatus::class`/`available_at`→immutable_datetime), `Audit\AuditRecord` (NUOVO namespace `App\Platform\Audit\`, `$timestamps=false`, casts `before`/`after`→array/`actor_role`→`ActorRole::class`; `occurred_at` UNCAST per la cast-list esplicita del task → rilegge string). Tutti `$guarded=[]` (i recorder 3.3/3.4 sono gli unici writer). **Split timestamps = correttezza non gusto**: le due append-only NON hanno colonne created/updated → timestamps ON farebbe scrivere a `create()` colonne inesistenti. Cast enum = floor value-set su entrambi gli engine (valore fuori-enum → `ValueError` via `BackedEnum::from()`, non catturato). Prossimo: **3.2 contract+registry**.

## Build & Quality Status
- Stack invariato: PHP 8.5.2 · Laravel 13.15.0 (`^13.8`) · Filament v5.6.7 + Livewire v4.3.1 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev; tests `:memory:`.
- Quality sul branch `ralph/foundations-domain-events-audit`: full suite **99/99 (386 assertioni)** ✅ (era 94/366; +5 = ModelsTest) · type_check 0 @ level max ✅ · lint ✅ · `openspec validate foundations-domain-events-audit --strict` ✅. `composer.json/lock` invariati vs main (zero nuove dep). Schema (gruppo 2) CHIUSO: 4 migrazioni domain + i model che le mappano.

## Active Change & Next Task
- **Attivo: `foundations-domain-events-audit` (F1 2/3) — APPROVED, loop in corso. 6/15 task fatti.**
- **Prossimo task: 3.2** — Consumer contract + registry (design D4): `App\Platform\Events\Contracts\DomainEventConsumer` (interface, `handle(DomainEvent $event): void`); `App\Platform\Events\ConsumerRegistry` singleton — `register(string $eventName, string $consumerClass, DeliveryMode $mode = DeliveryMode::Inline)` + `consumersFor(string $eventName): array`. La registrazione VALIDA che la classe implementi il contratto (`register('X', \stdClass::class)` → `InvalidArgumentException`); duplicato (event, consumer) idempotente O rifiutato (scegline uno, documenta nel docblock, testalo); modi oltre `Inline` strutturalmente impossibili oggi (enum a caso unico) — pin il gate queue-ADR con un test. Test `tests/Unit/Platform/ConsumerRegistryTest.php` con consumer anonymous-class/test-double; assert `consumersFor` ritorna FQCN in ordine di registrazione. **NB**: design D4 dice "plain singleton, consumer container-resolved a delivery-time"; il contract va sotto `Contracts/` (sottodir nuova). `DomainEvent` ora esiste come type-hint per `handle()`.
- Ordine successivo: 3.3 AuditRecorder · 3.4 DomainEventRecorder (scrivono via i model di 3.1) · 4.x delivery+sweep · 5.x hello-world+lane pgsql · 6.x docs+sweep finale.

## Blockers & Decisions Needed
- **Nessun blocker.** Gate integrità iter-1 già risolto; HEAD contiene `APPROVED` → re-run sicuro (`./ralph.sh --change foundations-domain-events-audit` riprende da 3.2).
- La lane CI pgsql (task 5.2) — e con essa il branch PG del CHECK actor_role (2.1/2.2), il partial index (2.3), i trigger immutabilità (2.4) — sarà vista verde la PRIMA volta solo al push umano pre-merge (il loop non pusha); parte dell'acceptance di review (design D8). I model 3.1 sono engine-agnostici (i cast Eloquent si comportano identici su SQLite/PG sotto i fallback documentati di 000001-000003).
- Carry-over: gate ADR aperti (identity/auth K · queue driver F4–F6 · object storage INV1 · hosting EU F7 · frontend TanStack S); `openspec/specs/module-architecture/spec.md` ha `Purpose: TBD` (tidy-up opzionale). Debiti semantic-verify W1/W2/W3/S1/S3 dal bootstrap (gate Module K).

## Open Patterns
- **Model platform** (3.1, ora in Codebase Patterns del progress): `$table` esplicito, `$guarded=[]`, `casts()` (Laravel 11+, `@return array<string,string>`); append-only → `public $timestamps=false`, ledger mutabile → timestamps tenuti; enum-cast = floor value-set (fuori-enum → `ValueError` non catturato = failure-case test); `jsonb`→`'array'`, `timestampTz`→`'immutable_datetime'`→`CarbonImmutable`. **PHPStan max + Larastan**: scansiona `database/migrations` di default ma il `SchemaAggregator` NON capisce `timestamptz` singolare → `@property` espliciti per `occurred_at`/`available_at` (Larastan mappa `immutable_datetime`→`CarbonImmutable`, annota uguale); `Model::__get` evita `property.notFound`; **Pint `fully_qualified_strict_types` gestisce docblock-type+import insieme** (scrivi `@property \Carbon\CarbonImmutable`, lui aggiunge `use` e accorcia). **Helper Pest GLOBALI tra file** → prefissa (`domainEventRow`/`immutabilityDomainEventRow`/`modelsDomainEventAttributes`).
- **Convenzioni F1 restano legge**: registry `Module::cases()`; red-proof per ogni regola arch NUOVA; **verify-vendor-first** (provato di nuovo a 3.1 sui cast/Larastan); il runner emette **JSON** (no TTY Pest); per-file `uses(RefreshDatabase::class)` (binding globale resta commentato).
- **Raw DDL branch-ata = `DB::statement`** (non `DB::unprepared`, vincolo `literal-string` di Larastan); migrazione Postgres-truthful con fallback SQLite behavior-preserving; trigger immutabilità rendono `domain_events`/`audit_records` insert-only (mai `update()`/`delete()` quei due model; solo `EventDelivery` round-trippa un update — i flip di status a 4.1).
