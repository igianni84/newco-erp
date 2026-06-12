---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-12
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-12 20:45 (ralph iter — task 4.1/15 ✅ green: InlineDeliveryExecutor + post-commit wiring)** — Gruppo 4 (delivery) **1/2**. NUOVO `App\Platform\Events\InlineDeliveryExecutor`: `deliver(array $domainEventIds): void` carica le delivery `pending` DUE (`available_at` null-or-past) ordinate `domain_event_id,id`, ognuna via `attempt()` — handler + flip `done`/attempts+1 in UNA `DB::transaction()` (exactly-once for DB effects); su throw rollbacka e `recordFailure()` in scrittura SEPARATA (attempts+1, backoff esponenziale `available_at`, `last_error` troncato, `failed` a max attempts), try/catch per-delivery isola i consumer (R4). Consumer risolto `container->make($fqcn)` + guard `instanceof`; evento via `DomainEvent::findOrFail`. Tunables via `Config::integer('events.sweep.*', <default 5/30/3600>)` → self-sufficient prima di `config/events.php` (4.2). MODIFICATO `DomainEventRecorder`: ctor inietta anche l'executor, dopo il fan-out registra `DB::afterCommit(fn () => executor->deliver([$event->id]))` (UN hook per `record()`, FIFO sul txn record → ordine causale, scartato su rollback). NUOVI doppi `Tests\Support\Platform\RecordingConsumer`/`FailingConsumer`. Test `tests/Feature/Platform/InlineDeliveryTest.php` (DatabaseMigrations) 6/27. Re-pointati 2 test di 3.4 (fan-out asserito DENTRO la txn; atomicità → count status-agnostico).

## Build & Quality Status
- Stack invariato: PHP 8.5.2 · Laravel 13.x · Filament v5 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev; tests `:memory:`.
- Quality su `ralph/foundations-domain-events-audit`: full suite **131/131 (489 assertioni)** ✅ (era 125/462, +6 = InlineDeliveryTest) · type_check 0 @ level max ✅ · lint ✅ · `openspec validate foundations-domain-events-audit --strict` ✅. `composer.json/lock` invariati vs main (zero nuove dep). Gruppi: schema (2) CHIUSO · substrate API (3) CHIUSO 4/4 · **delivery (4) 1/2** (executor+wiring 4.1 ✅; sweep 4.2 next).

## Active Change & Next Task
- **Attivo: `foundations-domain-events-audit` — APPROVED, loop in corso. 10/15 task fatti.**
- **Prossimo task: 4.2** — `php artisan events:sweep` + schedule + `config/events.php` (design D6). Il comando seleziona le delivery DUE (`pending` AND `available_at` null-or-past) ordinate `(consumer, domain_event_id)` ed esegue attraverso **il path 4.1**: l'executor ha `attempt(EventDelivery)` PRIVATO — 4.2 lo espone (o aggiunge un `deliverDue(): void` che lo condivide; stessa classe, refactor libero). `config/events.php` con `sweep.max_attempts` (5), `sweep.backoff_base_seconds` (30, ×2), `sweep.backoff_cap_seconds` (3600) — **l'executor li legge GIÀ via `Config::integer(..., <stesso default>)`, quindi il file rende solo espliciti/env-overridable, NESSUNA modifica all'executor**. Schedule in `routes/console.php` `everyThirtySeconds()` + `withoutOverlapping()` (verifica in vendor l'idiom scheduling Laravel 13 + sub-minute; comandi registrati per design D1 — `withCommands()` in `bootstrap/app.php` o provider). Test `tests/Feature/Platform/SweepTest.php`: crash-recovery rispecchia `seedPendingDelivery()` (righe committate SENZA l'hook recorder → lo sweep le consegna `done`); backoff/dead-letter/poison-no-stall con `CarbonImmutable::setTestNow()` (NON `$this->travelTo()` in closure Pest); pin lo schedule via introspezione scheduler (`Schedule::events()`, verifica API) o assert config-level.
- Ordine successivo: 5.1 events:demo + E2E pipeline · 5.2 lane CI pgsql · 6.1 docs/event-substrate.md · 6.2 sweep finale + mapping scenari→test.

## Blockers & Decisions Needed
- **Nessun blocker.**
- **D5 afterCommit landmine RISOLTO (4.1, record richiesto da 4.2/6.2 — già in progress.md):** il landmine "afterCommit non scatta sotto il wrapper RefreshDatabase" NON esiste su questo framework — il testing `DatabaseTransactionsManager` ritorna `shouldExecute($level) === ($level===1)` e salta il wrapper, quindi l'hook scatta al commit interno; il base manager scatta a livello 0 (DatabaseMigrations/produzione). Nessun fallback usato; scelto DatabaseMigrations per commit/rollback OSSERVABILI. `executeCallbacks()` è FIFO → hook per-record preservano l'ordine causale.
- **Hook PER-RECORD (non uno batched per-txn):** D3/D5 dicono "one hook per transaction" = contratto comportamentale (post-commit, ordine causale, mai su rollback), che il per-record soddisfa stateless (callback multipli sullo stesso txn record FIFO; rollback li scarta). Documentato.
- La lane CI pgsql (5.2) + i branch PG (CHECK 2.1/2.2, partial index 2.3, trigger 2.4) sono visti verdi solo al push umano pre-merge (il loop non pusha; design D8). Il codice 3.x/4.1 è engine-agnostic.
- Carry-over: gate ADR aperti (identity/auth K · queue driver F4–F6 · object storage INV1 · hosting EU F7 · frontend TanStack S); debiti semantic-verify W1/W2/W3/S1/S3 dal bootstrap (gate Module K).

## Open Patterns
- **`Config::integer($key, $default)`** (`Illuminate\Support\Facades\Config`, Laravel 11+) per leggere int da config a PHPStan max: ritorna `int` reale (lancia se non-int), `(int) config(...)` invece trippa `cast.int`. Il default nel 2° arg rende un servizio self-sufficient prima del suo `config/*.php`. Siblings `string/boolean/float/array`.
- **Container-resolve per FQCN + `instanceof` narrowing**: `$container->make($stringFqcn)` ritorna `mixed` (string, non class-string) → guard `if (! $x instanceof Contract) throw` narrowa + è il guard runtime per classe rinominata/rimossa. Il container risolve ANCHE classi anonime (empirico).
- **Execution-after-rollback resync**: handler+flip in una `DB::transaction()`; su throw `$delivery->refresh()` nel catch prima della scrittura di fallimento separata (attempts conta dal valore committato, non dalla mutazione in-memory di un `update()` rollbackato).
- **Named test-doubles sotto `Tests\Support\`** (PSR-4 `Tests\ => tests/`): autoloaded senza dump, NON raccolti da Pest (`tests/Pest.php` lega `TestCase` solo `->in('Feature')`), container-risolvibili per FQCN; sink statico `public static array $handled` resettato in `beforeEach` (il trait resetta il DB non gli statics).
- **Re-point dei test di un task precedente quando il downstream atterra**: i test fan-out di 3.4 asserivano pending/attempts-0 DOPO un record() committato; l'hook di 4.1 ora consegna post-commit → asserisci il contratto in-transaction DENTRO il `DB::transaction()` + atomicità status-agnostica (`EventDelivery::count()`). I test diventano più stretti.
- Convenzioni F1 restano legge: verify-vendor-first, runner JSON, per-file `uses(...)`, model platform conventions (3.1), `DB::statement` per DDL branch-ata (2.4), helper Pest GLOBALI → prefissa (`recordDeliveryEvent`/`seedPendingDelivery` unici); recorder fan-out + singleton ctor-iniettato (3.4); platform parla `string` non l'enum `Module` (3.3); atomicity test = DatabaseMigrations + tabella `cache` (3.4).
