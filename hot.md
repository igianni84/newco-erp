---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-12
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-12 09:00 (interactive — sessione ADR 1)** — **ADR "production DB engine" DECISA: PostgreSQL** (floor 17, managed EU; dev/test resta SQLite). `decisions/2026-06-12-production-db-engine.md` + riga INDEX scritti. Baseline: UTF-8 + default collation `C.UTF-8` (ICU opt-in per-superficie), **zero estensioni al lancio**, policy migration **"Postgres-truthful, SQLite-compatible"**, lane CI `pgsql` da introdurre nel primo change F1 con migration di dominio. L'ADR non decide: hosting provider (F7 — direzione probabile registrata: hyperscaler EU-region, non vincolante), audit store, queue, event substrate. **Azione umana pendente: 2 righe di CLAUDE.md da aggiornare a mano** (file protetto): riga tech-stack "Production engine: OPEN decision" e riga "Production DB engine" della tabella gate.

## Build & Quality Status
- **Invariato da 2026-06-11 (nessun codice toccato oggi):** PHP **8.5.2** · Composer **2.9.2** · Laravel **13.15.0** (`^13.8`) · Filament **v5.6.7** + Livewire **v4.3.1** · **Boost v2.4.10** (dev) · Pest **4.7.2** · PHPStan **2.2.2** · Larastan **3.10.0** · Pint **1.29.1**. SQLite dev; test su `:memory:`.
- Quality loop (ultimo run pre-merge 06-11): format ✅ · test **36/36 (99 assertions)** ✅ · type_check **0 @ level max** ✅ · lint ✅. **CI remota verde** (run 27346064961).

## Active Change & Next Task
- **Nessun change in-flight.** `openspec/specs/platform/spec.md` è l'unica living spec.
- **Gate "first Module 0 migration" sbloccato** (ADR 1 ✅; resta solo l'edit manuale CLAUDE.md di Giovanni).
- **Prossimo (GUIDE.md §3):** sessione **ADR 2 — event substrate + audit/financial event store** (gate per `foundations-domain-events-audit`; vincolo dal grill di oggi da portare in dote: eventi a cascata emessi in ordine causale dentro la stessa business transaction, Module A §12.4 → candidato naturale outbox-in-DB). Poi `/spec-to-change` per `foundations-modules-skeleton`.
- **Da incorporare nel primo change F1 con migration:** job CI `pgsql` (service container Postgres, matrix con SQLite, ogni push) + policy migration dell'ADR.

## Blockers & Decisions Needed
- **Azione umana:** le 2 righe CLAUDE.md di cui sopra (dettate in chat 2026-06-12).
- **Debiti dalla verifica semantica 06-11** (non bloccanti): **W1** `/up` senza check DB (manca listener `DiagnosingHealth`); **W2** `DatabaseSeeder` crea `test@example.com`/`password` + `canAccessPanel()` true per tutti → bonificare prima di staging (aggancio: ADR identity/auth, Module K); **W3** `composer.json` `php ^8.3` vs floor ≥8.4 (one-liner); **S1** commento fuorviante `phpstan-bootstrap.php`; **S3** `welcome.blade.php` copy hardcoded.
- Open ADR gates rimasti: identity/auth · queue driver · event substrate · audit store · object storage · hosting EU (direzione: hyperscaler EU-region) · frontend stack (TanStack SPA, founder direction).
- Credenziali sandbox esterne (Airwallex/Xero/HubSpot) prima dei change F6 — procurement umano.

## Open Patterns
- **Migrations (ADR 2026-06-12):** Postgres-truthful, SQLite-compatible — CHECK / partial / expression index ok ovunque; niente feature PG senza equivalente SQLite documentato; nessuna estensione PG al lancio.
- **La spec è tech-agnostic by design (DEC-073):** i vincoli per le ADR si estraggono dai PRD via subagent (contratti, NFR, invarianti) — mai cercando nomi di tecnologie, che non ci sono.
- **Merge ralph→main:** se main è antenato del branch, tree mergiato == tip branch — verificare con `git merge-base` + `git diff --cached <tip>` vuoto prima di concludere.
- **Verify-before-write vale anche per i doc:** constraint/versioni sempre da composer.json/lock, mai da memoria.
- **Doc↔reality guards:** `DevelopmentDocsTest` cross-checka la tabella versioni contro composer.lock — il refresh dei doc è enforced.
- Memory files via Edit/Write, mai Bash heredoc (regex git-guardrails matcha prose tipo "platform spec").
- Pin artifacts by executable form (`run: <cmd>`); Boost regen = `php artisan boost:install --guidelines -n`; Filament 5 auth FQCNs `Filament\Auth\Pages\*`.
- Pattern completi fase 0: `openspec/changes/archive/2026-06-11-bootstrap-laravel-app/progress.md` → `## Codebase Patterns`.
