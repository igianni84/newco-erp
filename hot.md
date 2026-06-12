---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-12
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-12 16:45 (ralph iter — task 2.3/15 ✅ green: TERZA tabella substrato `create_event_deliveries_table`)** — Il ledger di delivery per-consumer esiste su SQLite `:memory:`: una riga MUTABILE per (domain_event × consumer) con ciclo `pending→done|failed`, `attempts`, `available_at` di backoff, `timestamps()` (a differenza delle due tabelle append-only). FK `domain_event_id`→`domain_events.id`, unique `(domain_event_id, consumer)`, e il **partial index** `event_deliveries_pending_index ON (available_at) WHERE status='pending'` via raw `DB::statement` (il Blueprint installato non ha predicato partial fluente — verificato in vendor; `CREATE INDEX … WHERE` è valido IDENTICO su entrambi gli engine, design D2). Gruppo 2 (schema) ora 3/4. Prossimo: 2.4 trigger immutabilità (design D7), l'ultimo del gruppo schema.

## Build & Quality Status
- Stack invariato: PHP 8.5.2 · Laravel 13.15.0 (`^13.8`) · Filament v5.6.7 + Livewire v4.3.1 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev; tests `:memory:`.
- Quality sul branch `ralph/foundations-domain-events-audit`: full suite **88/88 (352 assertioni)** ✅ (era 79/342; +9 = EventDeliveriesSchemaTest) · type_check 0 @ level max ✅ · lint ✅ · `openspec validate foundations-domain-events-audit --strict` ✅. `composer.json/lock` invariati vs main (zero nuove dep). TRE migrazioni domain ora esistono (`…000001_create_domain_events_table`, `…000002_create_audit_records_table`, `…000003_create_event_deliveries_table`); resta 1 sola del gruppo schema (2.4 trigger immutabilità).

## Active Change & Next Task
- **Attivo: `foundations-domain-events-audit` (F1 2/3) — APPROVED, loop in corso. 4/15 task fatti.**
- **Prossimo task: 2.4** — migrazione `add_immutability_triggers` (design D7): blocchi `DB::unprepared()` branch-ati su `DB::getDriverName()`, **ENTRAMBI i branch implementati** (parità piena). `domain_events`: BEFORE UPDATE e BEFORE DELETE → raise con messaggio che CONTIENE il token stabile `immutable`. `audit_records`: BEFORE DELETE → raise; BEFORE UPDATE → raise TRANNE se cambiano SOLO `before`/`after` (la redazione GDPR — PG: trigger function che confronta le colonne strutturali con `IS DISTINCT FROM`; SQLite: clausola `WHEN` con `IS NOT` null-safe per colonna strutturale). UNA lista autorevole di colonne strutturali nel codice della migrazione guida ENTRAMBI i branch (anti-drift). **`event_deliveries` NON riceve trigger** — è mutabile by design (status/attempts/available_at cambiano nei retry). Stamp `2026_06_12_000004_…`. Test `tests/Feature/Platform/ImmutabilityTest.php`: i 5 scenari della delta-spec come red-paths (UPDATE/DELETE rifiutati su domain_events; UPDATE strutturale rifiutato, UPDATE solo-before/after permesso, DELETE rifiutato su audit_records) — assert `QueryException` + token `immutable` + riga invariata dopo, MAI SQLSTATE engine-specifici; seed righe via `DB::table()->insert()`. Questi test SONO i red-path → nessun red-proof separato; la lane pgsql 5.2 prova il branch PG senza modifiche ai test.
- Ordine successivo: 3.x model/registry/recorder (3.1 model con casts — `event_deliveries` TIENE timestamps; domain_events/audit_records NO) · 4.x delivery+sweep · 5.x hello-world+lane pgsql · 6.x docs+sweep finale.

## Blockers & Decisions Needed
- **Nessun blocker.** Gate integrità iter-1 (falso allarme `APPROVED` risucchiato) già risolto; HEAD contiene `APPROVED` → re-run sicuro. Per ripartire: `./ralph.sh --change foundations-domain-events-audit` riprende da 2.4.
- La lane CI pgsql (task 5.2) — e con essa il branch PG del CHECK actor_role (2.1+2.2), il partial index (2.3, che però gira anche su SQLite) e i trigger (2.4) — sarà vista verde la PRIMA volta solo al push umano pre-merge (il loop non pusha); parte dell'acceptance di review (design D8).
- Carry-over: gate ADR aperti (identity/auth K · queue driver F4–F6 · object storage INV1 · hosting EU F7 · frontend TanStack S); `openspec/specs/module-architecture/spec.md` ha `Purpose: TBD` (tidy-up opzionale, non a mano). Debiti semantic-verify W1/W2/W3/S1/S3 dal bootstrap (gate Module K).

## Open Patterns
- **Convenzioni F1 restano legge**: registry `Module::cases()` ovunque; red-proof per ogni regola arch nuova; **verify-vendor-first** (provato di nuovo a 2.3: assenza predicato partial nel Blueprint, firma `hasIndex`, `insertGetId` `@return int`, `timestamps()` nullable, `constrained()`→`id`); il runner emette **JSON** (no TTY Pest); per-file `uses(RefreshDatabase::class)`.
- **Migrazione Postgres-truthful** (provata 3×): fluent types con fallback SQLite behavior-preserving (`jsonb→text`, `timestampTz→datetime text`, `uuid→varchar`); constraint solo-PG (CHECK) in branch `DB::getDriverName()==='pgsql'` via `DB::statement` DOPO `Schema::create`, valori da `Enum::cases()`. **Partial index** (nuovo a 2.3): raw `DB::statement('CREATE INDEX <name> ON <t> (<col>) WHERE <pred>')` — valido su ENTRAMBI gli engine (a differenza del CHECK), nome esplicito stabile, literal dall'enum, assert PER NOME via `hasIndex($t,'<name>')` (portabile; `getIndexes()` non espone il predicato → green ⇒ DDL valida).
- **Assert schema portabili**: `Schema::hasColumns` (anche `->toBeFalse()` per pinnare l'ASSENZA deliberata di una colonna) + `hasIndex($t, [col,...]|'name', 'unique'|null)` (matcha array-colonne ESATTO **o** stringa-nome) + prove comportamentali (`insert` happy / `->throws(QueryException)` per NOT NULL/unique/FK, mai SQLSTATE). Row-builder `function fooRow(array $overrides=[]): array` DRY; FK provata sia inserendo un figlio valido sia un orphan rifiutato.
- **PHPStan max nei test**: helper plain vogliono `array<string,mixed>` param/return; mai castare risultato query (`->first()` è `stdClass|null`, `->value()` è `mixed` → asserisci con `->toEqual(...)`); `insertGetId` è già `int` (PHPDoc) → niente cast.
