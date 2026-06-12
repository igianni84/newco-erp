---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-12
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-12 10:31 (interactive — /spec-to-change)** — **`foundations-modules-skeleton` autorato e APPROVED** (F1 change 1/3, GUIDE §4). Capability nuova `module-architecture`: 6 requirement / 15 scenari, 9 task / 4 gruppi. Contenuto: nove moduli `app/Modules/{Catalog,Parties,Allocation,Procurement,Commerce,Inventory,Fulfilment,Finance,OperatorPanel}` + enum registry `App\Modules\Module` (lettere spec 0,K,A,D,S,B,C,E,Admin) + provider registrati; **boundary law**: superficie pubblica cross-module = SOLO `Contracts\*`+`Events\*`, piattaforma mai → moduli, `bootstrap/providers.php` composition-root esente; enforcement = **Pest arch test** (pest-plugin-arch già in lock, zero nuove dipendenze) con **red-proof obbligatorio** (violazione temporanea → rosso → rimozione, output in progress.md); panel Filament si sposta in `App\Modules\OperatorPanel\Providers\AdminPanelProvider` (task 1.3, suite esistente verde non modificata); `docs/module-template.md` con 9 sezioni = falsariga F2+. Decisioni intervista (founder, 2026-06-12): panel nel modulo (design D5); **tabelle dominio module-prefixed** `{modulo}_*` + `$table` esplicito, FK naturali, tabelle piattaforma senza prefisso, migrations globali (design D6). `openspec validate --strict` ✅.

## Build & Quality Status
- **Invariato (solo artefatti openspec, nessun codice):** PHP 8.5.2 · Composer 2.9.2 · Laravel 13.15.0 (`^13.8`) · Filament v5.6.7 + Livewire v4.3.1 · Boost v2.4.10 (dev) · Pest 4.7.2 (incl. pest-plugin-arch) · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev; test `:memory:`.
- Quality loop (ultimo run pre-merge 06-11): format ✅ · test 36/36 (99 assertions) ✅ · type_check 0 @ level max ✅ · lint ✅. CI remota verde.

## Active Change & Next Task
- **`foundations-modules-skeleton` APPROVED, in attesa del loop:** `caffeinate -i ./ralph.sh --change foundations-modules-skeleton 15` — **da terminale puro, mai dentro una sessione Claude**. _(Il loop gira ora su **Opus 4.8 contesto-1M + effort max** — pin in ralph.sh:188 `--model "${RALPH_MODEL:-claude-opus-4-8[1m]}"`; override `RALPH_MODEL`/`RALPH_EFFORT`, `CLAUDE_FLAGS` vince.)_ Primo task: 1.1 (enum Module). A `CHANGE_COMPLETE`: rituale GUIDE §2.7 (review branch → merge --no-ff → push → verifica semantica → archive).
- **Dopo:** `/spec-to-change foundations-domain-events-audit` (implementa ADR 2: tabelle+trigger, delivery runner+sweep, hello-world "DB + event bus + audit trail"; **incorpora lane CI pgsql + policy migration di ADR 1** — primo change con migration). Poi `foundations-money-i18n-flags`.

## Blockers & Decisions Needed
- **Azione umana (se non già fatta):** 3 edit CLAUDE.md della sessione ADR 2 (dettati in chat 2026-06-12 09:58).
- **Debiti verifica semantica 06-11** (non bloccanti): W1 `/up` senza check DB; W2 seeder `test@example.com` + `canAccessPanel()` true → bonificare prima di staging (gate Module K); W3 `composer.json` `php ^8.3` vs floor ≥8.4; S1 commento phpstan-bootstrap; S3 welcome.blade copy hardcoded.
- Open ADR gates: identity/auth (Module K) · queue driver (primo consumer `queued`, F4–F6) · object storage (INV1) · hosting EU (F7) · frontend TanStack SPA (Module S).
- Credenziali sandbox (Airwallex/Xero/HubSpot) prima dei change F6 — procurement umano.

## Open Patterns
- **Moduli (change approvato, da implementare):** superficie pubblica = `Contracts\*`+`Events\*`; tabelle `{modulo}_*` con `$table` esplicito; Filament resources del modulo nel modulo, discovery string-based nel panel; arch test iterano SEMPRE l'enum `Module` (mai liste hardcoded); emendare la boundary law solo nello stesso change che lo richiede + giustificazione in design.md; arch rule nuova ⇒ red-proof in progress.md.
- **Eventi (ADR 2026-06-12):** stato+evento+delivery atomici nella txn emittente; consumer inline = solo DB; I/O esterno = intent + processor di modulo; consumer idempotenti/order-tolerant; guardie = check in-transazione, MAI eventi; nomi eventi spec verbatim; payload PII-free; money int minor units, FX stringhe decimali.
- **Store immutabili:** `domain_events`/`audit_records` append-only via trigger PG+SQLite; migrations additive-only su quelle tabelle.
- **Migrations (ADR 1):** Postgres-truthful, SQLite-compatible; niente estensioni PG.
- **Spec tech-agnostic (DEC-073):** vincoli dai PRD via subagent (contratti/NFR/invarianti), mai cercando nomi di tecnologie.
- Verify-before-write anche per vendor API e doc; memory files via Edit/Write, mai Bash heredoc.
- Pattern completi fase 0: `openspec/changes/archive/2026-06-11-bootstrap-laravel-app/progress.md` → `## Codebase Patterns`.
