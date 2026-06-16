# Laravel — Rules (apply by default)

> Promoted from `hypotheses.md` (3 dated confirmations) or derived directly from a canonical decision / framework source / CI finding. A contradiction demotes a rule back to `hypotheses.md`.

## The framework's base `config/auth.php` is deep-merged UNDER the app's — you cannot remove `web`/`users` by editing the app file

**Rule.** Laravel's `Illuminate\Foundation\Bootstrap\LoadConfiguration` ships its own base config files inside the framework (`vendor/laravel/framework/config/*.php`) and merges them under the application's. For a fixed set of **mergeable nested keys** it does a per-key `array_merge(base, app)` (base first):

```php
// LoadConfiguration::mergeableOptions()
'auth'         => ['guards', 'providers', 'passwords'],
'broadcasting' => ['connections'],
'cache'        => ['stores'],
'database'     => ['connections'],
'filesystems'  => ['disks'],
'logging'      => ['channels'],
'mail'         => ['mailers'],
'queue'        => ['connections'],
```

`array_merge` **adds** the app's keys but **never deletes** a base key. Consequences for `auth` (and identically for the other mergeable configs):

1. **The framework's default `web` guard + `users` provider + `users` password broker are ALWAYS present in the merged `config('auth.*')`**, even when the application's `config/auth.php` omits them entirely. `config('auth.guards.web')` / `config('auth.providers.users')` are **non-null at runtime** regardless of the app file. A test asserting their absence (`->toBeNull()`) **fails**.
2. **Non-mergeable top-level keys ARE fully overridden** by the app file (the top-level `array_merge($base[$name], $config)` before the per-option loop). So `auth.defaults` (guard + passwords broker) is replaced wholesale → repointing `defaults.guard => 'operator'` takes effect cleanly. Same for any key not in the mergeable list.
3. **The leftover base entries are inert IF nothing resolves them.** After repointing the default guard, grep-verify that no code resolves the orphaned guard (`Auth::guard('web')`, `auth:web` middleware, or a **guard-less** `actingAs()`/`assertGuest()`/`Auth::check()` — those use the *default* guard, so they're safe once the default is repointed). If clean, the inert defaults break nothing even though the base `users` provider's model (`App\Models\User`) may have been deleted (a config string is never autoloaded; `::class` resolves at compile time without loading the class).
4. **To prove a principal/model shell was removed, use the empty source reference-sweep + the dropped DB table — NOT a config-key-absence assertion.** To *truly* strip the merged base entries at runtime you must mutate config in a service provider (`config(['auth.guards' => ['operator' => config('auth.guards.operator')], …])`); accept the inert defaults unless a security review requires the strip.

**Origin (dated).** 2026-06-15, `operator-auth-foundation` task 6.1 (remove the orphaned `User`). Deleting the `web` guard + `users` provider/broker from `config/auth.php` left them still present at runtime (`config('auth.guards')` showed both `web` and `operator` via tinker). Root-caused by reading `LoadConfiguration::loadConfigurationFile()` (line ~118–124) + `mergeableOptions()` (line ~140–152). The cutover succeeded functionally (default guard repointed to `operator`, `users` table dropped, suite 359/359 on SQLite + PG17); `AuthDefaultsTest` was written to assert the achievable end state (default guard/broker + a functional `actingAs(Operator)` default-guard proof + the table set) instead of the impossible config-key absence.

**Applies to.** Any change that adds/removes an auth guard, provider, password broker, DB connection, cache store, filesystem disk, log channel, mailer, or queue connection by editing the app config — most immediately the deferred **customer-identity / producer-identity** guard slices (they ADD guards/providers, which works; they must not assume they can REMOVE the inert `web`/`users`). Also: when an acceptance check greps for a removed FQCN over `app|config|database|tests`, the sweep includes **comments** — describe a removed class/factory without writing its literal token in those trees.

## Pint `fully_qualified_strict_types` rewrites a docblock `{@see \FQCN}` into a real `use` — reference not-yet-built or namespace targets in PROSE

**Rule.** A docblock `{@see \Fully\Qualified\Name}` (leading backslash = fully-qualified) makes Pint's `fully_qualified_strict_types` fixer ADD a real `use Fully\Qualified\Name;` and shorten the ref to `{@see Name}`. When the target does not exist yet (a forward-reference to a later task's class) or is a **namespace, not a class**, Pint emits a broken/meaningless import — a latent autoload + PHPStan trap; worse, when the auto-import crosses a module boundary it breaches **invariant 10** and turns `ModuleBoundariesTest` red. Use `{@see}` ONLY for a concrete class/method that already exists; reference a not-yet-built class or a namespace/concept in **prose** (plain backticked name). An UNQUALIFIED `{@see ClassInOtherNamespace}` (no leading `\`, not imported) is left untouched — the trap is specifically the fully-qualified form. After any docblock edit, re-run `vendor/bin/pint <file>` and eyeball the `use` block for an import that points at a namespace or a non-existent class.

**Confirmations (dated, cross-change).** 2026-06-12 `foundations-domain-events-audit`; 2026-06-13 `foundations-money-i18n-flags` (`{@see \App\Platform\I18n}` namespace → broken `use`); 2026-06-14 `catalog-product-spine` (use-cycle risk); 2026-06-15 `parties-core` (FQN-form sharpened); 2026-06-16 `catalog-lifecycle-approval` (escalated to invariant-10 / `ModuleBoundariesTest` breach + a `{@see lowercaseMethod()}`→cased-class variant); 2026-06-16 `parties-producer-lifecycle`. The quick-ref correction also lives in `lessons.md` (2026-06-13).

**Applies to.** Every change that adds rich docblocks — most of all module emitters / events / actions that cite sibling or cross-module classes.

## A PG-only `CHECK` goes in a `DB::getDriverName() === 'pgsql'` branch, with its enumerated values derived from `Enum::cases()`

**Rule.** A constraint only PostgreSQL can express (an enum-column `CHECK`, etc.) belongs in an `if (DB::getDriverName() === 'pgsql')` migration branch; the SQLite floor is the Eloquent enum **cast** (migrations stay Postgres-truthful, SQLite-compatible). **Derive the CHECK's allowed values from the backing enum's `::cases()`** (never a hand-typed `IN (...)` list) so the DB constraint can never drift from the PHP enum — one source of truth, two enforcers. Prove it engine-guarded: assert the named constraint **rejects** the bad insert on PG (assert the constraint NAME, never an engine SQLSTATE), wrapping the forbidden DML in a nested `DB::transaction()` (savepoint) so the verify-after-throw survives PG's aborted-transaction state.

**Confirmations (dated, cross-change).** 2026-06-12 `foundations-domain-events-audit` (`domain_events.actor_role`); 2026-06-14 `catalog-product-spine` (`lifecycle_state`); 2026-06-15 `parties-core` (per enum column; `party_type` proven on PG); 2026-06-16 `catalog-lifecycle-approval`. The engine-guarded CHECK-*test* idiom: 2026-06-13 `substrate-hardening`.

**Applies to.** Every migration introducing an enum-backed column. Pairs with the cross-engine portability rule in `knowledge/testing/rules.md`.
