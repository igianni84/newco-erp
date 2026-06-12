---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-12
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-12 15:20 (ralph iter — task 2.1/15 ✅ green: PRIMA migrazione domain `create_domain_events_table`)** — Il log append-only `domain_events` ora esiste su SQLite `:memory:` con l'envelope ADR completo, i due indici di lancio e il CHECK `actor_role` solo-PostgreSQL (design D2). Self-FK `causation_id`→`domain_events.id` funziona dentro lo stesso `Schema::create()` (provato dal test catena-causale). PHPStan max ha pescato 2 gap di typing nel test (fixati alla radice, non soppressi). Gruppo 2 (schema) ora 1/4. Prossimo: 2.2 `create_audit_records_table`.

## Build & Quality Status
- Stack invariato: PHP 8.5.2 · Laravel 13.15.0 (`^13.8`) · Filament v5.6.7 + Livewire v4.3.1 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev; tests `:memory:`.
- Quality sul branch `ralph/foundations-domain-events-audit`: full suite **71/71 (331 assertioni)** ✅ (era 64/323; +7 = DomainEventsSchemaTest) · type_check 0 @ level max ✅ · lint ✅ · `openspec validate foundations-domain-events-audit --strict` ✅. `composer.json/lock` invariati vs main (zero nuove dep). UNA migrazione domain ora esiste (`2026_06_12_000001_create_domain_events_table.php`); restano 3 (audit_records, event_deliveries, triggers).

## Active Change & Next Task
- **Attivo: `foundations-domain-events-audit` (F1 2/3) — APPROVED, loop in corso. 2/15 task fatti.**
- **Prossimo task: 2.2** — migrazione `create_audit_records_table` (design D2). **Riusa verbatim** lo shape di 2.1: stesso envelope CORE (`occurred_at` timestampTz · `module` · `actor_role` string NOT NULL **+ stesso blocco CHECK branch-ato `if (DB::getDriverName()==='pgsql')` con valori da `ActorRole::cases()`** · `actor_id` nullable · `entity_type`/`entity_id` · `correlation_id` uuid) **MENO** `event_id`/`name`/`schema_version`/`causation_id`, **PIÙ** `action` string NOT NULL · `before` jsonb nullable · `after` jsonb nullable · `authorization_basis` string NOT NULL · `id` PK. Indice `(entity_type, entity_id, id)`. Stamp `2026_06_12_000002_create_audit_records_table.php` (gira dopo domain_events). Test `tests/Feature/Platform/AuditRecordsSchemaTest.php` con `uses(RefreshDatabase::class)`: idiom `hasColumns`/`hasIndex` di 2.1; failure cases via `QueryException` su `actor_role` E `authorization_basis` mancanti.
- Ordine successivo: 2.3 event_deliveries (+ partial index `status='pending'`) · 2.4 trigger immutabilità (2 engine) · 3.x model/registry/recorder · 4.x delivery+sweep · 5.x hello-world+lane pgsql · 6.x docs+sweep finale.

## Blockers & Decisions Needed
- **Nessun blocker.** Gate integrità iter-1 (falso allarme `APPROVED` risucchiato) già risolto; HEAD contiene `APPROVED` → re-run sicuro. Per ripartire: `./ralph.sh --change foundations-domain-events-audit` riprende da 2.2.
- La lane CI pgsql (task 5.2) — e con essa il branch PG del CHECK actor_role (2.1) e dei trigger (2.4) — sarà vista verde la PRIMA volta solo al push umano pre-merge (il loop non pusha); parte dell'acceptance di review (design D8).
- Carry-over: gate ADR aperti (identity/auth K · queue driver F4–F6 · object storage INV1 · hosting EU F7 · frontend TanStack S); `openspec/specs/module-architecture/spec.md` ha `Purpose: TBD` (tidy-up opzionale, non a mano). Debiti semantic-verify W1/W2/W3/S1/S3 dal bootstrap (gate Module K).

## Open Patterns
- **Convenzioni F1 restano legge**: registry `Module::cases()` ovunque; red-proof per ogni regola arch nuova; **verify-vendor-first** (riprovato a 2.1: grammar SQLite, `Schema::hasIndex`/`getIndexes`, `DB::getDriverName()`); il runner emette **JSON** (no TTY Pest); per-file `uses(RefreshDatabase::class)` (binding globale resta commentato).
- **Nuove (consolidate in progress.md §Codebase Patterns)**: (1) **migrazione Postgres-truthful** — fluent types con fallback SQLite behavior-preserving (`jsonb→text`, `timestampTz→datetime text`, `uuid→varchar`); constraint solo-PG (CHECK) in branch `DB::getDriverName()==='pgsql'` via `DB::statement` DOPO `Schema::create`, valori derivati da `Enum::cases()` (no drift), fallback SQLite documentato inline; (2) **assert schema portabili** — `Schema::hasColumns` + `hasIndex($t, [col,...], 'unique'|null)` matcha l'array colonne esatto (niente nome-indice generato) + prove comportamentali (`insert` happy / `->throws(QueryException)` per NOT NULL/unique, mai SQLSTATE; self-FK provato con riga figlia→id padre); row-builder `function fooRow(array $overrides=[])` DRY; (3) **PHPStan max nei test** — helper plain vogliono `array<string,mixed>` param/return; mai castare risultato query (`->first()` è `stdClass|null`, `->value()` è `mixed`) — asserisci con `->toEqual(...)`.
