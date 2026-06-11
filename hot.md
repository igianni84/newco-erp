---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-11
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-11 (fase-0 review + approvazione)** — Rilievi Giovanni applicati al change bootstrap: versioni pinnate (Laravel 13.x + Filament 5.x, ADR supersede), task Laravel Boost aggiunto (guida AI Filament), direzione frontend TanStack registrata, CLAUDE.md aggiornato con OK esplicito. Change strict-valid e **APPROVATO** (marker creato 10:27 su istruzione esplicita di Giovanni, commit 88f9b71 + approve commit). No application code yet — pronto per il primo lancio del loop.

## Build & Quality Status
- No Laravel app installed yet — quality commands not runnable (expected; bootstrap change does this).
- CI: not configured yet (now task **3.3** of bootstrap change, after renumbering).
- Guardrails live (60/60 hook tests green, 2026-06-11): `.claude/hooks/protected-paths.sh` (PreToolUse Edit/Write), `.claude/hooks/git-guardrails.sh` (write-verbs any-mode; push/APPROVED/archive loop-only), `ralph.sh` integrity gate (exit 5). See GUIDE.md §5.
- Note: OpenSpec CLI (1.4.1, core profile) has no `verify` command — semantic verification is prompt-based, see GUIDE.md §2.7.

## Active Change & Next Task
- `openspec/changes/bootstrap-laravel-app/` — **10 task** (era 9: aggiunto 3.2 Laravel Boost; CI→3.3, docs→3.4), strict-valid, **APPROVED (2026-06-11)**.
- Pinned: `laravel/laravel:^13.0` (create-project), `filament/filament:^5.0`, `laravel/boost --dev` per ADR `decisions/2026-06-11-stack-versions-and-filament-ai-tooling.md` (supersede del tech-stack ADR). Boost non deve mai toccare CLAUDE.md (acceptance nel task 3.2).
- Next human action (GUIDE §2.6): chiudere la finestra Claude e dal terminale `caffeinate -i ./ralph.sh --change bootstrap-laravel-app 2` (osservare le prime 2 iterazioni), poi rilanciare con `15`.
- After bootstrap: ADR sessions #1 (DB engine) e #2 (event substrate), poi `/spec-to-change` per i tre foundations changes (GUIDE.md §3–4).
- Strategy notes (2026-06-11): foundations changes must bake in mechanical invariant enforcement — Pest arch tests for module boundaries, domain-event registry, Money value object, i18n skeleton; PostgreSQL CI lane from Module A (`lockForUpdate()` no-op on SQLite).

## Blockers & Decisions Needed
- **Frontend TanStack (direzione founder, 2026-06-11):** consumer storefront + producer portal in TypeScript/TanStack, niente PHP frontend; Filament/Livewire solo operator panel. ADR formale al gate Module S storefront via grill-with-docs (API layer, customer auth, i18n ×6 fuori da Laravel localization, SSR/SEO, EU hosting). Dettagli: `.claude/memory/frontend-stack-direction.md`.
- **Filament Blueprint** (premium, licenza a pagamento): NON adottato — acquisto è decisione di Giovanni se/quando vorrà.
- Open ADR gates (CLAUDE.md table): DB engine, identity/auth, queue, event substrate, audit store, object storage, hosting EU, frontend stack. None blocks the bootstrap change.
- External sandbox credentials (Airwallex/Xero/HubSpot) needed before F6 changes — human-procured.

## Open Patterns
- None yet — first loop iterations will seed `knowledge/` and the change's `## Codebase Patterns`.
