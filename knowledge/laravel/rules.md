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

**Confirmations (dated, cross-change).** 2026-06-12 `foundations-domain-events-audit`; 2026-06-13 `foundations-money-i18n-flags` (`{@see \App\Platform\I18n}` namespace → broken `use`); 2026-06-15 `catalog-product-spine` (use-cycle risk); 2026-06-15 `parties-core` (FQN-form sharpened); 2026-06-16 `catalog-lifecycle-approval` (escalated to invariant-10 / `ModuleBoundariesTest` breach + a `{@see lowercaseMethod()}`→cased-class variant); 2026-06-16 `parties-producer-lifecycle`; 2026-06-20 `operator-console-catalog-master` (`ViewProductMaster`'s `{@see \App\Modules\Catalog\Exceptions\IllegalLifecycleTransition}` — a *concrete, existing* class — auto-imported into a `use` that breaches the OperatorPanel `{Models, Actions}` carve-out; reworded to prose, kept only `{@see RuntimeException}`). The quick-ref correction also lives in `lessons.md` (2026-06-13).

**Applies to.** Every change that adds rich docblocks — most of all module emitters / events / actions that cite sibling or cross-module classes.

## A domain/transition rejection is a localized exception built via a static factory + a load-bearing `(string) __()` cast

**Rule.** A module's domain-guard or transition-guard rejection (invariant 12 forbids hardcoded user-facing strings) is a `RuntimeException` subclass whose message comes from `lang/`, constructed through a **static named factory** — `::cannotActivate($from)`, `::forId($id)`, `::parentNotActive(...)` — that returns `new self((string) __('<module>.<group>.<key>', [...placeholders]))`. The **`(string)` cast is load-bearing**: Larastan (PHPStan max) types `__($key, …)` as `mixed`, and the `RuntimeException` constructor demands `string` — this is the one place a `(string)` cast on a translation is intentional (unlike `(string) $mixed` on a DB scalar, which the cross-engine rule bans). The named factory centralizes the key + placeholder contract so no call-site drifts. English baseline only (`lang/en/<module>.php`, dotted nested keys); the other 5 locales fall back per-key. Keep PII out of the message (e.g. `DuplicateCustomerEmail` omits the email).

**Confirmations (dated, cross-change).** 2026-06-15 `catalog-product-spine` (origin — `DuplicateProductMasterIdentity`, `UnsupportedProductType`, `InsufficientCompositeConstituents`; `lang/en/catalog.php`); 2026-06-15 `parties-core` (`MissingClubProducer`, `MissingAgreementProducer`, `DuplicateCustomerEmail`, `DuplicateProfileForClub`; `forId(int)`; `lang/en/parties.php`); 2026-06-16 `catalog-lifecycle-approval` (`IllegalLifecycleTransition` with `cannotSubmit/cannotActivate/cannotRetire/cannotReopen` → private `build(key, from, entity)`; plus `ApprovalGovernanceViolation`, `ActivationCascadeViolation`, …); 2026-06-16 `parties-producer-lifecycle` (`IllegalProducerTransition`, `IllegalProducerAgreementTransition`, `IllegalClubTransition` — `::cannotX({Entity}Status $from): self`).

**Applies to.** Every domain/transition guard that rejects an operator action with a localized reason — most immediately each new module's create/transition Actions. *(The DDL-side enum-`CHECK` rule that used to sit here moved to `knowledge/data-model/rules.md` on 2026-06-16.)*
