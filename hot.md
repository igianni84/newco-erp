---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-11
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-11 14:23 (interactive — chiusura rituale §2.7)** — **FASE 0 CHIUSA.** `bootstrap-laravel-app` mergiato su main (`merge --no-ff`, commit 5d474ba), pushato, branch ralph cancellato. **Prima run CI remota: SUCCESS in 30s** (run 27346064961). Verifica semantica (subagent, report-only): **CLEAN — 0 CRITICAL**, 3 WARNING + 3 SUGGESTION registrati in log.md e qui sotto. `openspec archive` eseguito: il change vive in `changes/archive/2026-06-11-bootstrap-laravel-app/` e i delta sono confluiti in **`openspec/specs/platform/spec.md`** (3 requirement — la prima living spec del repo).

## Build & Quality Status
- **Version snapshot (docs/development.md + guardato da DevelopmentDocsTest):** PHP **8.5.2** · Composer **2.9.2** · Laravel **13.15.0** (`^13.8`) · Filament **v5.6.7** (`^5.0`) + Livewire **v4.3.1** · **Boost v2.4.10** (dev) · Pest **4.7.2** · PHPStan **2.2.2** · Larastan **3.10.0** · Pint **1.29.1**. SQLite dev; test su `:memory:`.
- Quality loop (rieseguito pre-merge ~14:00): format ✅ · test **36/36 (99 assertions)** ✅ · type_check **0 @ level max** ✅ · lint ✅ · `openspec validate --strict` ✅.
- **CI remota verde** sul merge commit; ogni push la rilancia.

## Active Change & Next Task
- **Nessun change in-flight.** Archive completato; `openspec/specs/` ora è la living documentation (capability `platform`).
- **Prossimo (GUIDE.md roadmap F1):** sessione **ADR 1 — production DB engine** (PostgreSQL vs MySQL, gate per la prima migration Module 0) e **ADR 2 — event substrate + audit store** (gate per `foundations-domain-events`). Poi `/spec-to-change` per la prima fetta foundations (`foundations-modules-skeleton`).

## Blockers & Decisions Needed
- **Debiti dalla verifica semantica** (non bloccanti, da assorbire nei prossimi change): **W1** `/up` risponde 200 al solo boot — nessun check DB (manca listener `DiagnosingHealth`), la prosa del requirement platform promette di più; **W2** `DatabaseSeeder` crea `test@example.com`/`password` e `User::canAccessPanel()` ritorna `true` per tutti → login /admin da seed, bonificare prima di staging (si aggancia all'ADR identity/auth, Module K); **W3** `composer.json` dichiara `php ^8.3` ma il floor di progetto è ≥8.4 (fix one-liner); **S1** commento fuorviante in `phpstan-bootstrap.php` (forza 1G anche su limiti maggiori); **S3** `welcome.blade.php` copy hardcoded — l'invariante i18n la vieta su superfici reali.
- Open ADR gates: production DB engine · identity/auth · queue driver · event substrate · audit store · object storage · hosting EU · frontend stack (TanStack SPA, founder direction).
- Credenziali sandbox esterne (Airwallex/Xero/HubSpot) prima dei change F6 — procurement umano.

## Open Patterns
- **Merge ralph→main:** se main è antenato del branch (loop partito da main fermo), il tree mergiato == tip branch e nulla può perdersi — verificare con `git merge-base` + `git diff --cached <tip>` vuoto prima di concludere.
- **Verify-before-write vale anche per i doc:** constraint/versioni sempre da composer.json/lock, mai da memoria.
- **Doc↔reality guards:** `DevelopmentDocsTest` cross-checka la tabella versioni contro composer.lock — il refresh dei doc è enforced.
- Memory files via Edit/Write, mai Bash heredoc (regex git-guardrails matcha prose tipo "platform spec").
- Pin artifacts by executable form (`run: <cmd>`); Boost regen = `php artisan boost:install --guidelines -n`; Filament 5 auth FQCNs `Filament\Auth\Pages\*`.
- Pattern completi del change: `openspec/changes/archive/2026-06-11-bootstrap-laravel-app/progress.md` → `## Codebase Patterns`.
