---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-12
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-12 23:55 (ralph iter — task 6.2/15 ✅ green: final traceability + quality sweep)** — Gruppo 6 (docs + final sweep) **2/2 CHIUSO**. **TUTTI I 15 TASK `[x]` → CHANGE COMPLETE.** 6.2 = il task di chiusura, nessun codice di produzione nuovo salvo il test del gap. (1) I 5 Quality Commands in ordine tabella CLAUDE.md tutti verdi. (2) `openspec validate foundations-domain-events-audit --strict` verde. (3) Walk di OGNI `#### Scenario:` nei due delta spec — **29 scenari** (26 `event-substrate` + 3 `platform`) — tabella mapping scenario→test-che-lo-copre registrata in `progress.md`, **nessuno scoperto**. (4) Trovato **1 GAP** (event-substrate "Retries are per-consumer", scenario 12) e CHIUSO sul posto con +1 test in `tests/Feature/Platform/SweepTest.php`: nessun test esistente guidava una delivery GIÀ-fallita (attempts≥1) a successo su retry (crash-recovery parte da attempts 0; backoff/dead-letter non riescono mai; poison-no-stall salta la riga in backoff). Il nuovo test: seed previously-failed (RecordingConsumer, pending/attempts1/backoff-elapsed) + sibling già-`done` (FailingConsumer) sullo STESSO evento → `events:sweep` → retried va done/attempts2 e SOLO lui gira, sibling intatto done/attempts1 (FailingConsumer-as-sibling = prova non-vacua del "not re-executed"). (5) Due proof richiesti confermati in `progress.md`: red-proof arch 1.1 @ 14:26 + risoluzione D5 afterCommit 4.1 @ 20:45 + Codebase Pattern. (6) `git status` = solo `SweepTest.php` (non protetto); `git diff main -- composer.json composer.lock` vuoto.

## Build & Quality Status
- Stack invariato: PHP 8.5.2 · Laravel 13.x · Filament v5 · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev; tests `:memory:`.
- Quality su `ralph/foundations-domain-events-audit`: full suite **151/151 (598 assertioni)** ✅ (era 150/592, +1 test/+6 = il nuovo pin scenario-12 in `SweepTest`) · type_check 0 @ level max ✅ · lint ✅ · `openspec validate foundations-domain-events-audit --strict` ✅. `composer.json/lock` invariati vs main (zero nuove dep, l'invariante della change). **Tutti i 6 gruppi CHIUSI:** schema (2) 4/4 · substrate API (3) 4/4 · delivery (4) 2/2 · hello-world+CI (5) 2/2 · docs+sweep (6) 2/2.

## Active Change & Next Task
- **`foundations-domain-events-audit` — APPROVED, 15/15 task `[x]`. CHANGE_COMPLETE emesso questa iter.**
- **PROSSIMO PASSO = UMANO (il loop NON archivia/mergia):** review del branch `ralph/foundations-domain-events-audit` → merge su main → push (la prima CI run dal vero, incl. la **lane pgsql** che prova i branch PG di 2.1 CHECK / 2.3 partial index / 2.4 trigger — design D8) → `/opsx:verify` → `openspec archive foundations-domain-events-audit --yes`. Vedi memory `closing-ritual-delegation`: Giovanni può delegare la close a Claude (verify-first).
- Dopo l'archive, la prossima change si prepara con `/spec-to-change` dalla `spec/05-release/Build_Workplan_v0.3-MVP.md` (Phase 1 substrato CHIUSO; prossima fase F2+ = primo modulo reale che emette eventi veri — gate ADR queue-driver atteso F4–F6).

## Blockers & Decisions Needed
- **Nessun blocker. Change pronta per la review umana.**
- La lane CI pgsql (5.2) + i branch PG (CHECK 2.1/2.2, partial index 2.3, trigger 2.4) sono visti verdi solo al push umano pre-merge (D8). Il codice 3.x/4.x/5.x è engine-agnostic; i test behavior-only (mai SQLSTATE) coprono entrambi i motori, la lane pgsql ri-prova il ramo PG.
- Carry-over (non bloccanti, fuori da questa change): gate ADR aperti — identity/auth (Module K) · queue driver (F4–F6) · object storage (INV1) · hosting EU (F7) · frontend TanStack (Module S); debiti semantic-verify W1/W2/W3/S1/S3 dal bootstrap (gate Module K).

## Open Patterns
- **Traceability-walk come task di chiusura (6.2)**: mappa OGNI `#### Scenario:` a un test che esercita IL SUO `WHEN`, non uno adiacente. Uno scenario il cui WHEN COMPONE due comportamenti già testati separatamente (qui: "retry riesce" + "sibling non ri-eseguito") può comunque essere SCOPERTO se nessun singolo test esercita la composizione — la walk è esattamente dove emerge. Il deliverable è la tabella in `progress.md` + lo sweep verde; un gap prende il suo test sul posto.
- **Doc-pin test idiom (6.1)**: sibling `*DocsTest.php` con reader globale (nome non-collidente) + `toContain` per ogni fatto deciso; non-vacuità = reader-throwing. Pin verbatim (whitespace SQL + `immutable`≠`immutability`). Refresh di un sibling-doc preserva i suoi token pinnati.
- **Lane CI per un secondo engine = JOB SEPARATO, mai matrix (5.2, D8)**: lint/PHPStan engine-independent UNA volta (`quality`), solo `php artisan test` due volte (`tests-pgsql`). `postgres:17` + `pg_isready` + `ports:['5432:5432']` + `DB_HOST:127.0.0.1`; job-env reale batte `phpunit.xml <env>` non-force. Stessa shape per un futuro Redis-lane (gate queue).
- **Re-runnable operator command su tabelle append-only (5.1, D9)**: ogni run APPENDE (UUIDv7 fresco), ogni STATE-write è `updateOrInsert` idempotente; consumer demo-only registrati sul singleton DENTRO `handle()`. `Artisan::call()`+`Artisan::output()` per le asserzioni output a PHPStan max (il Pest `artisan()` è inusabile sul ramo `int`).
- **Substrato (1.x–4.x)**: tutto sotto `App\Platform` (boundary law: mai importare `App\Modules\**` → arch test). Recorder ride la transazione del CALLER (`NotInTransactionException` se level 0), envelope con UUIDv7 + money minor-units + FX decimal-string (mai float, D18). Delivery inline post-commit + `events:sweep` at-least-once (backoff/dead-letter, tunables `config/events.php`). Immutabilità via trigger DB parità SQLite/PG (token `immutable`; audit redaction-only su before/after).
