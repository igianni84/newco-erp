# Laravel — Hypotheses (test when possible; Confirmations: N/3)

> An observation becomes a hypothesis once it has a plausible mechanism. Three dated confirmations promote it to `rules.md`; a contradiction demotes it back here (or to `knowledge.md`). Mechanics: `.claude/CLAUDE.md` → Knowledge System.

## `config/` is outside PHPStan's analysis paths — pin every config invariant with a Feature config-test

**Hypothesis.** `phpstan.neon` analyzes only `app`, `database`, `routes`, `tests` — `config/` is deliberately excluded (so `env()` may be read there). A typo, wrong arity, or a stale **derived** value in a `config/*.php` file is therefore invisible to static analysis; its only guard is a tiny **Feature** test (the container resolves there) asserting the resolved value equals its source of truth — `expect(config('i18n.supported'))->toBe(SupportedLocale::values())`, `expect(config('database.connections.pgsql.timezone'))->toBe('UTC')`. No DB / `RefreshDatabase` needed.

**Confirmations: 2/3** (need 1 more distinct change).
- 2026-06-13 `foundations-money-i18n-flags` — origin; `config/i18n.php` pinned by `I18nConfigTest`, then the same recipe for the published `config/pennant.php` (`PennantInstalledTest`).
- 2026-06-13 `substrate-hardening` — `config('database.connections.pgsql.timezone')` pinned to `'UTC'` in `tests/Feature/EnvironmentTest.php` (runs on both lanes).
- *(Weaker, NOT counted as the 3rd: 2026-06-16 `catalog-lifecycle-approval` exercises `config/catalog.php`'s `approval.role_count` through behavioral governance tests rather than a pure `expect(config(...))` equality — same "config is unanalysed, prove it via a booted test" mechanism, looser form.)*

**Applies to.** Any invariant that lives in a `config/*.php` file (derived arrays, published-package keys, env-overridable defaults).

## Pint promotes a docblock `{@see \Fully\Qualified\Name}` into a real `use` import — which can manufacture a module-boundary violation out of prose

**Hypothesis.** Pint's `fully_qualified_strict_types` fixer (on in the Laravel preset) does not merely shorten FQCNs in *code*: it scans **docblocks**, shortens any fully-qualified `{@see \A\B\C}` to `{@see C}`, and **adds `use A\B\C;` to the file** to keep it resolvable. The import is indistinguishable from a hand-written one. Pest's architecture expectations (`expect($ns)->not->toUse('App\Modules\Allocation')`, `tests/Architecture/ModuleBoundariesTest.php:121`) read real imports — so a purely explanatory cross-module `{@see}` in a docblock becomes a **genuine dependency** and reds the boundary test. In this repo, where docblocks routinely explain a class by naming its collaborators, that is a live trap rather than a curiosity.

**Prescription.** Reference a class the file does not actually depend on as **plain backticked prose** (`` `ApproveProfile` ``), never `{@see \FQCN}`. Reserve `{@see}` for symbols the file genuinely imports or for its own methods (`{@see methodName()}`). After writing a docblock-heavy file, always `git diff` the `use` block after the first `pint` run — the fixer edits the imports silently and the diff is the only place it shows.

**Confirmations: 1/3** (need 2 more distinct changes).
- `parties-hero-package` task 1.2 — `Support\ClubSeatOccupancy`'s docblock explained the seat set by naming the four Actions that consume it (`{@see \App\Modules\Parties\Actions\ApproveProfile}` etc.). Pint silently added four `use App\Modules\Parties\Actions\*` imports to a `Support/` class that depends on none of them. Within-module here, so nothing went red — but the identical prose naming a `Modules\Allocation\*` symbol would have imported it and tripped task 4.1's *"no Module K source file imports a `Modules\Allocation\*` symbol"* assertion, and invariant 10 with it. *(Confirmation date = archive-dir date once archived.)*

**Applies to.** Every module-boundary assertion in `tests/Architecture/ModuleBoundariesTest.php`, the `Platform`-must-not-use-`App\Modules` pin (`:215`), and the per-class `not->toUse` guards on the deferred read-ports (`HeroPackageCapacityReaderBindingTest:118`). Highest risk where a docblock's job is to explain a **deferred seam** by naming the module that will own it.
