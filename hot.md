---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-12
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-12 16:05 (ralph iter — task 2.2/15 ✅ green: SECONDA tabella substrato `create_audit_records_table`)** — Il trail di audit operatore esiste su SQLite `:memory:` con l'envelope CORE condiviso con `domain_events` (no `event_id`/`name`/`schema_version`/`causation_id`) + le colonne audit-specifiche (`action` NOT NULL, `before`/`after` jsonb **nullable** per la redazione GDPR D7, `authorization_basis` NOT NULL), l'indice `(entity_type,entity_id,id)` e il CHECK `actor_role` solo-PostgreSQL liftato verbatim da 2.1 (design D2). Gruppo 2 (schema) ora 2/4. Prossimo: 2.3 `create_event_deliveries_table` (ledger per-consumer + partial index `status='pending'`).

## Build & Quality Status
- Stack invariato: PHP 8.5.2 · Laravel 13.15.0 (`^13.8`) · Filament v5.6.7 + Livewire v4.3.1 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev; tests `:memory:`.
- Quality sul branch `ralph/foundations-domain-events-audit`: full suite **79/79 (342 assertioni)** ✅ (era 71/331; +8 = AuditRecordsSchemaTest) · type_check 0 @ level max ✅ · lint ✅ · `openspec validate foundations-domain-events-audit --strict` ✅. `composer.json/lock` invariati vs main (zero nuove dep). DUE migrazioni domain ora esistono (`…000001_create_domain_events_table.php`, `…000002_create_audit_records_table.php`); restano 2 (event_deliveries, trigger immutabilità).

## Active Change & Next Task
- **Attivo: `foundations-domain-events-audit` (F1 2/3) — APPROVED, loop in corso. 3/15 task fatti.**
- **Prossimo task: 2.3** — migrazione `create_event_deliveries_table` (design D2): `id` PK · `domain_event_id` FK→`domain_events.id` · `consumer` string · `status` string NOT NULL default `pending` · `attempts` unsignedSmallInteger default 0 · `available_at` timestampTz nullable · `last_error` text nullable · **timestamps** (questa tabella SÌ — a differenza di domain_events/audit_records). Unique `(domain_event_id, consumer)`; **partial index su `status='pending'`** — fluent se il Blueprint installato esprime il predicato (VERIFICARE in vendor: `Blueprint::index()` accetta `->where()`? altrimenti un raw `CREATE INDEX … WHERE status = 'pending'` valido su ENTRAMBI gli engine, design D2). Stamp `2026_06_12_000003_…` (gira dopo audit_records). Test `tests/Feature/Platform/EventDeliveriesSchemaTest.php` con `uses(RefreshDatabase::class)`: idiom `hasColumns`/`hasIndex` di 2.1–2.2; il partial index è visibile via `Schema::getIndexes()`/`PRAGMA index_list`/`pg_indexes` — scegliere l'assert portabile e DOCUMENTARE la scelta; failure case = insert duplicato `(domain_event_id, consumer)` → `QueryException`.
- Ordine successivo: 2.4 trigger immutabilità (2 engine, design D7) · 3.x model/registry/recorder · 4.x delivery+sweep · 5.x hello-world+lane pgsql · 6.x docs+sweep finale.

## Blockers & Decisions Needed
- **Nessun blocker.** Gate integrità iter-1 (falso allarme `APPROVED` risucchiato) già risolto; HEAD contiene `APPROVED` → re-run sicuro. Per ripartire: `./ralph.sh --change foundations-domain-events-audit` riprende da 2.3.
- La lane CI pgsql (task 5.2) — e con essa il branch PG del CHECK actor_role (2.1+2.2) e dei trigger (2.4) — sarà vista verde la PRIMA volta solo al push umano pre-merge (il loop non pusha); parte dell'acceptance di review (design D8).
- Carry-over: gate ADR aperti (identity/auth K · queue driver F4–F6 · object storage INV1 · hosting EU F7 · frontend TanStack S); `openspec/specs/module-architecture/spec.md` ha `Purpose: TBD` (tidy-up opzionale, non a mano). Debiti semantic-verify W1/W2/W3/S1/S3 dal bootstrap (gate Module K).

## Open Patterns
- **Convenzioni F1 restano legge**: registry `Module::cases()` ovunque; red-proof per ogni regola arch nuova; **verify-vendor-first** (prossimo banco di prova a 2.3: il predicato del partial index nel Blueprint installato); il runner emette **JSON** (no TTY Pest); per-file `uses(RefreshDatabase::class)` (binding globale resta commentato).
- **Migrazione Postgres-truthful** (consolidato in progress.md §Codebase Patterns, ora provato 2×): fluent types con fallback SQLite behavior-preserving (`jsonb→text`, `timestampTz→datetime text`, `uuid→varchar`); constraint solo-PG (CHECK) in branch `DB::getDriverName()==='pgsql'` via `DB::statement` DOPO `Schema::create`, valori da `Enum::cases()` (no drift), fallback SQLite documentato inline. Il blocco CHECK `actor_role` si lifta verbatim cambiando solo tabella + nome constraint.
- **Assert schema portabili**: `Schema::hasColumns` (anche `->toBeFalse()` per pinnare l'ASSENZA deliberata di una colonna — guard anti-drift, nuovo a 2.2) + `hasIndex($t,[col,...],'unique'|null)` matcha l'array colonne esatto (niente nome-indice generato) + prove comportamentali (`insert` happy / `->throws(QueryException)` per NOT NULL/unique, mai SQLSTATE). Row-builder `function fooRow(array $overrides=[])` DRY.
- **PHPStan max nei test**: helper plain vogliono `array<string,mixed>` param/return; mai castare risultato query (`->first()` è `stdClass|null`, `->value()` è `mixed`) — asserisci con `->toEqual(...)`.
