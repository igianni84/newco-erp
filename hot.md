---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-12
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-12 09:58 (interactive — sessione ADR 2)** — **ADR "event substrate + audit/financial event store" DECISA (un file, chiude ENTRAMBI i gate):** transactional outbox su app DB — `domain_events` append-only scritto nella transazione emittente È insieme outbox, audit log decennale e store eventi finanziari; `audit_records` separato (before/after, authorization_basis); `event_deliveries` per-(evento×consumer) prunabile (R4 = retry indipendenti); ledger di processo nei moduli (FSM Xero di E referenzia eventi per id). Consegna: `inline` default lancio (solo DB, mai I/O esterno), `queued` post-ADR-queue, I/O esterno = intent + processor schedulato di modulo; guardie no-oversell/compliance FUORI dal substrato (check in-transazione, mai eventi); ATP B→A inline, porta `transactional` dichiarata non costruita (Phase 5 aperta). Semantiche: at-least-once, exactly-once su effetti DB, consumer idempotenti/order-tolerant cross-txn, no FIFO bloccante, dead-letter in loco. Immutabilità: trigger su entrambi i motori + REVOKE/grant column-level in prod + migrations additive-only; eventi PII-free (immutabilità assoluta), audit records con redazione GDPR via ruolo `redactor`. No partizioni al lancio (opzione conservata). File: `decisions/2026-06-12-event-substrate-and-audit-store.md` + INDEX + CONTEXT.md (sezione Events & Audit). **Azione umana pendente: 3 edit CLAUDE.md a mano** (2 righe gate da cancellare + 1 riga Architecture da sostituire — dettate in chat 09:58; opzionale: raffinamento riga queue driver).

## Build & Quality Status
- **Invariato (nessun codice toccato):** PHP 8.5.2 · Composer 2.9.2 · Laravel 13.15.0 (`^13.8`) · Filament v5.6.7 + Livewire v4.3.1 · Boost v2.4.10 (dev) · Pest 4.7.2 · PHPStan 2.2.2 · Larastan 3.10.0 · Pint 1.29.1. SQLite dev; test `:memory:`.
- Quality loop (ultimo run pre-merge 06-11): format ✅ · test 36/36 (99 assertions) ✅ · type_check 0 @ level max ✅ · lint ✅. CI remota verde (run 27346064961).

## Active Change & Next Task
- **Nessun change in-flight.** `openspec/specs/platform/spec.md` unica living spec.
- **Gate F1 tutti sbloccati** (ADR 1 ✅ DB engine + ADR 2 ✅ event substrate/audit store; restano solo gli edit manuali CLAUDE.md di Giovanni).
- **Prossimo (GUIDE §4, F1):** `/spec-to-change` per `foundations-modules-skeleton`, poi `foundations-domain-events-audit` (implementa ADR 2: tabelle+trigger, delivery runner+sweep, hello-world Workplan Phase 1 "DB + event bus + audit trail"), poi `foundations-money-i18n-flags`.
- **Da incorporare nel primo change F1 con migration di dominio:** lane CI `pgsql` + policy migration (ADR 1) — candidato naturale: `foundations-domain-events-audit`.

## Blockers & Decisions Needed
- **Azione umana:** 3 edit CLAUDE.md della sessione ADR 2 (dettati in chat 2026-06-12 09:58).
- **Debiti verifica semantica 06-11** (non bloccanti): W1 `/up` senza check DB (manca listener `DiagnosingHealth`); W2 seeder `test@example.com`/`password` + `canAccessPanel()` true → bonificare prima di staging (aggancio ADR identity, Module K); W3 `composer.json` `php ^8.3` vs floor ≥8.4 (one-liner); S1 commento `phpstan-bootstrap.php`; S3 `welcome.blade.php` copy hardcoded.
- Open ADR gates rimasti: identity/auth (Module K) · queue driver (gate ridefinito da ADR 2: primo consumer `queued`, atteso F4–F6; requisiti pre-fissati at-least-once + per-job delay) · object storage (INV1) · hosting EU (F7, direzione hyperscaler) · frontend TanStack SPA (Module S).
- Credenziali sandbox esterne (Airwallex/Xero/HubSpot) prima dei change F6 — procurement umano.

## Open Patterns
- **Eventi (ADR 2026-06-12):** stato+evento+delivery committano atomici nella txn emittente; consumer inline = solo DB, mai I/O esterno; I/O esterno = intent + processor schedulato di modulo; consumer SEMPRE idempotenti e order-tolerant cross-txn (watermark per-entity se latest-wins); guardie/compliance = check in-transazione via read contract, MAI eventi; nomi eventi spec verbatim; payload eventi PII-free; money int minor units, FX stringhe decimali mai float.
- **Store immutabili:** `domain_events`/`audit_records` append-only via trigger (identici PG+SQLite); migrations su queste tabelle additive-only; REVOKE+grant column-level solo prod (fallback documentato).
- **Migrations (ADR 1):** Postgres-truthful, SQLite-compatible — CHECK/partial/expression index ok ovunque; niente estensioni PG.
- **Spec tech-agnostic (DEC-073):** vincoli ADR dai PRD via subagent (contratti, NFR, invarianti), mai cercando nomi di tecnologie.
- Verify-before-write anche per i doc; memory files via Edit/Write, mai Bash heredoc; merge ralph→main: `git merge-base` + diff staged vuoto vs tip.
- Pattern completi fase 0: `openspec/changes/archive/2026-06-11-bootstrap-laravel-app/progress.md` → `## Codebase Patterns`.
