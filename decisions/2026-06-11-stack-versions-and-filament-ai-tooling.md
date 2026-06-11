---
type: decision
status: active
date: 2026-06-11
supersedes: 2026-06-11-tech-stack-laravel-filament.md
---

## Decision: Pin Laravel 13.x + Filament 5.x; adopt Filament's AI-assisted development tooling

Carries forward the stack choice of the superseded ADR (PHP ≥ 8.4, Laravel + Filament core, Pest, Larastan, Pint, SQLite dev/test) with two amendments:

1. **Versions pinned at major level** — Laravel **13.x** (`laravel/framework:^13.0`; released 2026-03-17, PHP ≥ 8.3, supported through 2028-03) and Filament **5.x** (`filament/filament:^5.0`; released 2026-01-16, the Livewire-v4 major; current 5.6.7). Exact minor/patch versions are still recorded in `docs/development.md` + the change's `progress.md` at install time, and `composer.lock` freezes from there. Major upgrades (e.g. Laravel 14) become deliberate future changes, never implicit.
2. **Filament's official AI guidance adopted** (https://filamentphp.com/docs/5.x/introduction/ai) — **Laravel Boost** as dev dependency (`composer require laravel/boost --dev` + `php artisan boost:install`, selecting the Laravel and Filament guidelines) so autonomous loop iterations write idiomatic framework code; https://filamentphp.com/docs/llms.txt is the agent-facing docs index, referenced from `docs/development.md`. Boost guideline output lands in `AGENTS.md` / Boost's own guideline files and must NEVER touch the protected `CLAUDE.md` (verified via `git status`, same discipline as the rsync merge in bootstrap task 1.1). **Filament Blueprint** (premium, license-keyed) is NOT adopted — separate founder purchase decision if ever wanted.

## Context

Founder review (fase 0, 2026-06-11) of `bootstrap-laravel-app`: Giovanni wants the APPROVED marker to approve a *known* target ("Laravel 13.x, Filament 5.x") rather than "whatever is stable when the loop runs", plus adherence to Filament's own AI-development recommendations — this repo is built by AI agents in the ralph loop, so agent-facing guidelines are first-class infrastructure, not nice-to-have.

## Alternatives considered

- **"Latest stable at execution time"** (the superseded ADR) — maximally fresh, but the human reviewer cannot know exactly what the loop will install.
- **Exact patch pins** (13.14.0 / 5.6.7) — needlessly brittle; `composer.lock` already gives reproducibility, major-level pins give reviewability.
- **Adopting Blueprint now** — premium license cost; deferred to an explicit founder decision.

## Reasoning

1. Reviewability of the approval gate: a pinned major is something a human can sign off on.
2. Both majors verified current stable (June 2026): Laravel 13.14.0, Filament 5.6.7. PHP floors (≥ 8.3 / ^8.2) are satisfied by the project rule PHP ≥ 8.4 and local PHP 8.5.2.
3. Boost is Filament's own recommended path for AI-agent code quality — it directly serves the ralph loop, the primary builder of this codebase.

## Trade-offs accepted

- Freshness → predictability: a new Laravel/Filament major released mid-build is ignored until a deliberate upgrade change.
- One more dev dependency (Boost) and its guideline files to keep coherent with `CLAUDE.md`/`AGENTS.md`.

## Open sub-decisions (each needs its own ADR before its gate)

| Sub-decision | Gate |
|---|---|
| Production DB engine (PostgreSQL vs MySQL) | first Module 0 migration |
| Identity/auth (first-party vs IdP; customer vs operator) | Module K |
| Queue driver (Redis+Horizon vs database) | first async workflow |
| Domain-event substrate (in-process + outbox vs broker) | first cross-module event |
| Audit/financial event store (immutability mechanism) | first financial event |
| Object storage (invoices, statements) | INV1 issuance |
| Hosting (EU data residency) | staging environment |
| Consumer/producer frontend stack — **founder direction registered 2026-06-11: TanStack (TypeScript SPA, https://tanstack.com/); no PHP-rendered consumer/producer frontend, so Livewire/Inertia are ruled out for those surfaces**. The formal ADR still happens at the gate (grill-with-docs) because the direction carries implications to design: API layer shape, customer auth, i18n ×6 outside Laravel localization, SEO/SSR, EU hosting of a second deployable. Filament/Livewire remains operator-panel-only. | Module S storefront |

## References

https://filamentphp.com/docs/5.x/introduction/ai · https://filamentphp.com/docs/llms.txt · https://laravel.com/docs/13.x/releases · https://laravel-news.com/filament-5 · spec/04-decisions (DEC-073: stack is dev-team scope) · [[2026-06-11-tech-stack-laravel-filament]] · [[2026-06-11-modular-monolith-architecture]]
