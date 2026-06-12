---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-12
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-12 14:26 (ralph — `foundations-domain-events-audit` task 1.1/15 ✅ green)** — Creato il root piattaforma `App\Platform` con i tre enum substrate (`ActorRole`, `DeliveryStatus`, `DeliveryMode`) ed esteso la legge boundary a coprirlo (design D1). Red-proof eseguito e registrato in progress.md. Primo task del change chiuso; restano 14.

## Build & Quality Status
- Stack invariato: PHP 8.5.2 · Laravel 13.15.0 (`^13.8`) · Filament v5.6.7 + Livewire v4.3.1 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev; tests `:memory:`.
- Quality sul branch `ralph/foundations-domain-events-audit`: full suite **64/64 (323 assertioni)** ✅ (era 60/317 su main; +4 = EnumsTest) · type_check 0 @ level max ✅ · lint ✅ · `openspec validate foundations-domain-events-audit --strict` ✅. `composer.json/lock` invariati vs main (zero nuove dep). Ancora ZERO migrazioni domain (arrivano a 2.1).

## Active Change & Next Task
- **Attivo: `foundations-domain-events-audit` (F1 2/3) — APPROVED, loop in corso. 1/15 task fatti.**
- **Prossimo task: 2.1** — migrazione `create_domain_events_table` (design D2). Envelope ADR completo: `id` bigint PK · `event_id` uuid unique · `name` · `schema_version` smallint def 1 · `module` · `occurred_at` timestampTz · `actor_role` string NOT NULL **+ CHECK sui 4 valori solo su PostgreSQL** (raw branch su driver; fallback SQLite = NOT NULL + enum cast, documentato inline) · `actor_id` nullable · `entity_type`/`entity_id` · `correlation_id` uuid · `causation_id` nullable FK self→`domain_events.id` · `payload` jsonb. Indici `(entity_type,entity_id,id)` e `(name,id)`. Test `tests/Feature/Platform/DomainEventsSchemaTest.php` con `uses(RefreshDatabase::class)`.
- **Verify-vendor-first PRIMA di scrivere 2.1** (lessons.md): grammar `jsonb()`/`timestampTz()` su SQLite, API `Schema::hasColumns()`/`Schema::getIndexes()`, come esprimere il CHECK PG branch-ato via `DB::statement`/`unprepared` con `DB::getDriverName()`. Insert senza `actor_role` → `QueryException` (failure case).
- Ordine successivo: 2.2 audit_records · 2.3 event_deliveries (+ partial index `status='pending'`) · 2.4 trigger immutabilità (2 engine) · 3.x recorder/registry/model · 4.x delivery+sweep · 5.x hello-world+lane pgsql · 6.x docs+sweep finale.

## Blockers & Decisions Needed
- Nessuno bloccante. La lane CI pgsql (task 5.2) sarà vista verde la PRIMA volta solo al push umano pre-merge (il loop non pusha) — parte dell'acceptance di review (design D8).
- Carry-over: gate ADR aperti (identity/auth K · queue driver F4–F6 · object storage INV1 · hosting EU F7 · frontend TanStack S); `openspec/specs/module-architecture/spec.md` ha `Purpose: TBD` (tidy-up opzionale, non a mano). Debiti semantic-verify W1/W2/W3/S1/S3 dal bootstrap (gate Module K).

## Open Patterns
- **Convenzioni F1 1/3 restano legge**: registry `Module::cases()` ovunque; red-proof obbligatorio per ogni regola arch nuova; verify-vendor-first; doc-pin idiom; il runner emette **JSON** (no TTY Pest).
- **Nuove (consolidate in progress.md §Codebase Patterns)**: piattaforma sotto `App\Platform` (enum in `App\Platform\Events`); enum = string-backed PascalCase→snake_case, docblock Module-density, test con mappa case→value verbatim `->toBe` order-sensitive + un `from('bad')`→`ValueError`; **emendamento boundary law per nuovo root piattaforma** = aggiungi a `$platformNamespaces` + red-proof (temp class type-referencing un modulo → RED nomina coppia → rimuovi → GREEN → tree pulito); `App\Platform` ora è in array → `App\Platform\Money` (F1 3/3) NON richiede nuovo emendamento; messaggio fail arch = `Expecting '<src>' not to use '<tgt>'.`; per-file `uses(RefreshDatabase::class)` (binding globale resta commentato).
