---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-12
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-12 19:10 (ralph iter — task 3.3/15 ✅ green: AuditRecorder)** — Gruppo 3 (substrate API) **3/4**. NUOVI: `App\Platform\Audit\AuditRecorder` (`record(action, module, actorRole, actorId, entityType, entityId, before, after, authorizationBasis, correlationId=null): AuditRecord` — envelope core con UTC `occurred_at` via `CarbonImmutable::now('UTC')`, `correlation_id` default a `Str::uuid7()` fresco, scrive via il model `AuditRecord`, **ZERO** righe `event_deliveries`) e `App\Platform\Events\NotInTransactionException` (eccezione dedicata CONDIVISA dai due recorder; static `forRecording(string $what)`; transaction guard `DB::transactionLevel() === 0`). Test `tests/Feature/Platform/AuditRecorderTest.php` su **DatabaseMigrations** (non RefreshDatabase — vedi Blockers). **DECISIONE BOUNDARY (vincola 3.4 + ogni emitter F2+):** il recorder prende `string $module` NON `Module|string` — importare `App\Modules\Module` sotto `App\Platform` fa scattare l'arch test `it_forbids_platform_code...` (provato RED). Gli emitter passano `Module::X->value`; D1 (boundary canonica) supera lo sketch `Module|string` di D3.

## Build & Quality Status
- Stack invariato: PHP 8.5.2 · Laravel 13.x · Filament v5 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev; tests `:memory:`.
- Quality su `ralph/foundations-domain-events-audit`: full suite **114/114 (418 assertioni)** ✅ (era 108/398; +6 = AuditRecorderTest) · type_check 0 @ level max ✅ · lint ✅ · `openspec validate foundations-domain-events-audit --strict` ✅. `composer.json/lock` invariati vs main (zero nuove dep). Schema (gruppo 2) CHIUSO; gruppo 3: model 3.1 + contract/registry 3.2 + AuditRecorder 3.3 fatti, resta **3.4 DomainEventRecorder**.

## Active Change & Next Task
- **Attivo: `foundations-domain-events-audit` — APPROVED, loop in corso. 8/15 task fatti.**
- **Prossimo task: 3.4** — `App\Platform\Events\DomainEventRecorder` (design D3): `record(...)` con lo STESSO transaction guard (riusa `NotInTransactionException::forRecording('a domain event')`); envelope: `event_id` UUIDv7 app-side (`Str::uuid7()`, già verificato esistente in vendor), UTC `occurred_at`, `correlation_id` default = il PROPRIO `event_id` (NON un uuid fresco indipendente come l'audit — design D3), `causation_id` nullable, `schema_version` 1, **`module` = `string`** (NON `Module|string` — decisione boundary sopra), `payload` array. PERSISTE l'evento + UNA riga `pending` `event_deliveries` per consumer registrato (`app(ConsumerRegistry::class)->consumersFor($name)`), tutto nella txn del chiamante; POI hook `DB::afterCommit` che passa gli id all'executor (4.1, NON questo task). Test `tests/Feature/Platform/DomainEventRecorderTest.php` su **DatabaseMigrations** (serve commit/rollback OSSERVABILE + guard a livello 0): scenari atomic-commit (stato+evento+righe pending), rollback-scarta-tutti-e-tre, outside-txn rifiutato, FX `'1.0842'` sopravvive come string, id monotòni in una txn, provenance per (entity_type, entity_id) in ordine id, correlation_id == event_id se non passato. Registra due fake consumer → assert due righe `pending`.
- Ordine successivo: 4.1 InlineDeliveryExecutor + afterCommit · 4.2 events:sweep + schedule + config/events.php · 5.1 hello-world events:demo + E2E · 5.2 lane CI pgsql · 6.1 docs/event-substrate.md · 6.2 sweep finale + mapping scenari→test.

## Blockers & Decisions Needed
- **Nessun blocker.**
- **DatabaseMigrations per i test dei recorder** (3.3 stabilito, 3.4+ idem): un guard `transactionLevel() === 0` è intestabile sotto RefreshDatabase (wrapper → livello ≥1, design D3 "satisfied trivially"); DatabaseTruncation è OUT (il suo DELETE per-tabella colpisce i trigger immutabilità append-only); DatabaseMigrations (migrate:fresh = DDL, non-triggerato) lascia i test a livello 0 → wrap delle scritture in `DB::transaction()` esplicito, guard test bare. Bonus: `DB::transaction()` COMMITTA in modo osservabile (necessario per gli atomicity/rollback test di 3.4 — RefreshDatabase non mostra un commit reale).
- La lane CI pgsql (5.2) + i branch PG (CHECK 2.1/2.2, partial index 2.3, trigger 2.4) sono visti verdi solo al push umano pre-merge (il loop non pusha; parte dell'acceptance di review, design D8). Il codice 3.1–3.3 è engine-agnostic.
- Carry-over: gate ADR aperti (identity/auth K · queue driver F4–F6 · object storage INV1 · hosting EU F7 · frontend TanStack S); `openspec/specs/module-architecture/spec.md` ha `Purpose: TBD` (tidy opzionale). Debiti semantic-verify W1/W2/W3/S1/S3 dal bootstrap (gate Module K).

## Open Patterns
- **Platform parla `string` non l'enum `Module`** (boundary law, 3.3 → Codebase Patterns + `knowledge/architecture/rules.md`): ogni API platform che porta un'identità di modulo la tipa `string` (`Module::X->value` o `'platform'`), mai `App\Modules\Module`. Vincola 3.4 + ogni emitter F2+. I test possono importare `Module` (l'arch suite scansiona solo i sorgenti `App\Platform`).
- **`NotInTransactionException` condivisa** in `App\Platform\Events` (static `forRecording`) — 3.4 la riusa con `forRecording('a domain event')`. Extends `RuntimeException`.
- **Test-typing PHPStan max** (3.3): helper tipato con param defaulted (non spread di `array<string,mixed>` → degrada ogni param a `mixed`); `CarbonImmutable::setTestNow()` static (non `$this->travelTo()`, irrisolvibile in closure Pest dove `$this` = `TestCall`) + `afterEach` reset; non `{@see}` una classe non ancora esistente (Pint auto-importa l'FQCN → `use` di classe mancante). Larastan inferisce il return di `DB::transaction(fn () => x)` e `app(Foo::class)`.
- Convenzioni F1 restano legge: verify-vendor-first, runner JSON, per-file `uses(...)`, model platform conventions (3.1), `DB::statement` per DDL branch-ata (2.4), helper Pest GLOBALI → prefissa (`recordTestAudit` unico).
