# Laravel — Hypotheses (test when possible; Confirmations: N/3)

> An observation becomes a hypothesis once it has a plausible mechanism. Three dated confirmations promote it to `rules.md`; a contradiction demotes it back here (or to `knowledge.md`). Mechanics: `.claude/CLAUDE.md` → Knowledge System.

## `config/` is outside PHPStan's analysis paths — pin every config invariant with a Feature config-test

**Hypothesis.** `phpstan.neon` analyzes only `app`, `database`, `routes`, `tests` — `config/` is deliberately excluded (so `env()` may be read there). A typo, wrong arity, or a stale **derived** value in a `config/*.php` file is therefore invisible to static analysis; its only guard is a tiny **Feature** test (the container resolves there) asserting the resolved value equals its source of truth — `expect(config('i18n.supported'))->toBe(SupportedLocale::values())`, `expect(config('database.connections.pgsql.timezone'))->toBe('UTC')`. No DB / `RefreshDatabase` needed.

**Confirmations: 2/3** (need 1 more distinct change).
- 2026-06-13 `foundations-money-i18n-flags` — origin; `config/i18n.php` pinned by `I18nConfigTest`, then the same recipe for the published `config/pennant.php` (`PennantInstalledTest`).
- 2026-06-13 `substrate-hardening` — `config('database.connections.pgsql.timezone')` pinned to `'UTC'` in `tests/Feature/EnvironmentTest.php` (runs on both lanes).
- *(Weaker, NOT counted as the 3rd: 2026-06-16 `catalog-lifecycle-approval` exercises `config/catalog.php`'s `approval.role_count` through behavioral governance tests rather than a pure `expect(config(...))` equality — same "config is unanalysed, prove it via a booted test" mechanism, looser form.)*

**Applies to.** Any invariant that lives in a `config/*.php` file (derived arrays, published-package keys, env-overridable defaults).

## A localized `cannot_*` reason restates the guard it fires from — so widening the guard silently makes the copy lie, and no test can catch it

**Hypothesis.** This repo's illegal-transition copy does not merely *report* a rejection, it *restates the rule*: `'cannot_approve' => 'Cannot approve this Profile from state :state. A Profile is approved only from applied.'` The second sentence is a specification claim embedded in an operator-facing string. When an Action's from-state set changes, that claim becomes false — and **the test suite is structurally blind to it**:

1. The exception unit tests (`MembershipTransitionExceptionsTest`) call `__($key, ['state' => $x])` and assert the result *contains* `$x` — proving interpolation, never the sentence.
2. Those tests deliberately choose an `$x` **absent from every template** (the file says so at `:20-21`), so a template naming new states still passes.
3. The behavioural tests build their expected message from **the same template** (`ProfileApprovalCapacityGateTest:226`: `toThrow(…, (string) __('parties.profile.cannot_approve', […]))`), so both sides move together and the assertion is a tautology with respect to the copy.

A false rule-restatement therefore reaches production green. It is user-facing (CLAUDE.md invariant 12) and, being prose, invisible to `grep` sweeps written around *implementation* tokens.

**Prescription.** A task that widens or narrows a from-state guard MUST, in the same iteration, run `grep -n "only from" lang/*/[module].php` and reconcile every hit. Prefer stating the rule once (the Action's docblock) over restating it in copy; where copy must carry it — an operator needs to know which states are legal — treat the string as **part of the guard's diff**, not as adjacent text.

**Confirmations: 1/3** (need 2 more distinct changes).
- `parties-hero-package` task 2.2 widened `ApproveProfile` to `{applied, waiting_list}` and shipped `cannot_approve` still reading *"approved only from applied"*; full suite, PHPStan max and Pint were all green across that commit. Task 2.3 found it only because `cannot_reject` — the line directly below — had to change for the same reason, and corrected both. The change's own § 7.1 residual sweep greps `UNCAPPED|uncapped|deferred Module-A seam|WaitingListJoined` and matches **neither** key. *(Confirmation date = archive-dir date once archived.)*

**Applies to.** Every `IllegalProfileTransition` / `IllegalCustomerTransition` / `IllegalProducerTransition` / `IllegalKycTransition` / `IllegalLifecycleTransition` reason in `lang/en/{parties,catalog}.php` — 20+ keys, each ending in a rule-restating sentence. Highest risk on the demand-side Profile FSM, whose from-state sets this change is actively widening.

## The `Schema` facade erases the schema builder's `list<string>` — read `DB::connection()->getSchemaBuilder()` instead of casting

**Hypothesis.** `Illuminate\Support\Facades\Schema` declares its passthroughs as `@method static array getTableListing(...)` and `@method static array getColumnListing(string $table)`, while the concrete `Illuminate\Database\Schema\Builder` declares `@return list<string>` on both. The facade therefore **loses** the element type: under PHPStan max every table name and every column name arrives as `mixed`, so `array_filter($tables, fn (string $t) => …)` draws `argument.type`, `str_contains($column, …)` draws "expects string, mixed given", and `$table.'.'.$column` draws `binaryOp.invalid`. The tempting fixes — a `(string)` cast, an inline `@var`, a widened `mixed` closure parameter — are all forbidden by this repo's PHPStan instructions, and each would hide the fact that a **schema-inspection test cannot type its own data**. The typed API is one hop away: `DB::connection()->getSchemaBuilder()` returns the concrete `Builder` (`DB`'s facade declares `@method static \Illuminate\Database\Connection connection(...)`; `Connection::getSchemaBuilder(): \Illuminate\Database\Schema\Builder`), and the generics survive.

**Prescription.** In any test that inspects schema (`AC-*-XM-*` absence criteria, migration guards, column set-pins), wrap the builder once in a local helper — `function <prefix>Schema(): SchemaBuilder { return DB::connection()->getSchemaBuilder(); }` — and call `->getTableListing()` / `->getColumnListing()` on it. Two engine notes that bite immediately: `getTableListing()` is **schema-qualified by default**, returning `main.parties_clubs` on SQLite and `public.parties_clubs` on PG17, so pass `schemaQualified: false` whenever the assertion compares bare names; and column ORDER differs across engines, so compare with `toEqualCanonicalizing`, never `toBe`.

**Confirmations: 1/3** (need 2 more distinct changes).
- `parties-hero-package` task 4.1 — `HeroPackageCapacityBoundaryTest` implements `AC-K-XM-20`'s literal verification method (*"inspect Module K entity schemas … assert absence"*). Written against the facade it was Pint-clean, fully green on both engines, and drew **5 PHPStan errors**. Rewritten through `heroBoundarySchema()` it typechecks at 0 with no cast and no suppression, and the column-adding mutant still reds both pins on SQLite and PG17. *(Confirmation date = archive-dir date once archived.)*

**Applies to.** Every schema-absence assertion the remaining acceptance criteria demand — `AC-A-XM-20`, `AC-D-XM-18`, `AC-E-XM-20` and the Module B five-dimension `StockPosition` guards all specify "inspect the schema, assert absence", and all will reach for `Schema::` first.
