# Laravel — Hypotheses (test when possible; Confirmations: N/3)

> An observation becomes a hypothesis once it has a plausible mechanism. Three dated confirmations promote it to `rules.md`; a contradiction demotes it back here (or to `knowledge.md`). Mechanics: `.claude/CLAUDE.md` → Knowledge System.

## `config/` is outside PHPStan's analysis paths — pin every config invariant with a Feature config-test

**Hypothesis.** `phpstan.neon` analyzes only `app`, `database`, `routes`, `tests` — `config/` is deliberately excluded (so `env()` may be read there). A typo, wrong arity, or a stale **derived** value in a `config/*.php` file is therefore invisible to static analysis; its only guard is a tiny **Feature** test (the container resolves there) asserting the resolved value equals its source of truth — `expect(config('i18n.supported'))->toBe(SupportedLocale::values())`, `expect(config('database.connections.pgsql.timezone'))->toBe('UTC')`. No DB / `RefreshDatabase` needed.

**Confirmations: 2/3** (need 1 more distinct change).
- 2026-06-13 `foundations-money-i18n-flags` — origin; `config/i18n.php` pinned by `I18nConfigTest`, then the same recipe for the published `config/pennant.php` (`PennantInstalledTest`).
- 2026-06-13 `substrate-hardening` — `config('database.connections.pgsql.timezone')` pinned to `'UTC'` in `tests/Feature/EnvironmentTest.php` (runs on both lanes).
- *(Weaker, NOT counted as the 3rd: 2026-06-16 `catalog-lifecycle-approval` exercises `config/catalog.php`'s `approval.role_count` through behavioral governance tests rather than a pure `expect(config(...))` equality — same "config is unanalysed, prove it via a booted test" mechanism, looser form.)*

**Applies to.** Any invariant that lives in a `config/*.php` file (derived arrays, published-package keys, env-overridable defaults).
