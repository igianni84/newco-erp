# Design — foundations-money-i18n-flags

## Context

Repo state at authoring (2026-06-13): the first two F1 foundations changes are merged + archived — `foundations-modules-skeleton` (F1 1/3: nine module roots, the `App\Modules\Module` registry, the always-on `tests/Architecture/` suite, `docs/module-template.md`) and `foundations-domain-events-audit` (F1 2/3: the `App\Platform` substrate — `domain_events` / `audit_records` / `event_deliveries`, the `ActorRole` enum, the two recorders, inline delivery + sweep, three-layer immutability, the `pgsql` CI lane). Suite 151/151 green (SQLite + PG 17 lanes), PHPStan max 0, Pint clean. Both prior changes explicitly named `foundations-money-i18n-flags` as their successor and deferred to it: the money/i18n/feature-flag helpers and the reusable `actor_role` helper. The substrate code forward-references it literally — `DomainEventRecorder`'s docblock says a float in an FX field "is a caller bug **the F1 3/3 value objects will make unrepresentable**."

This change closes that gap. It is **not** module work: Build Workplan § 2 Phase 1 ("Key decisions to make") lists the multi-currency dual-record schema pattern (D18, "full pattern"), the i18n schema pattern (D2, six-locale-ready), the feature-flag infrastructure (D12, carry i) and the `actor_role` audit envelope (carry iii) as **Phase-1 platform concerns**. Each is realised here as decided by the spec and the existing ADRs; where this document goes beyond them it is realization detail, not re-decision.

Three forks the spec does not resolve at the value-object level were decided by default and are surfaced for founder veto at the approval gate (see Open Questions): include the `DualCurrencyAmount` composite now (vs. defer to Module E); remediate `welcome.blade.php` to a minimal placeholder (vs. fully localize it); and reduce carry (iii) to the one gate-safe `ActorContext` seam (vs. treat it as already-satisfied by the existing enum + envelope).

A separate `substrate-hardening` change is already staged (audit fixes incl. the `composer.json` `php ^8.3`→`^8.5` bump). This change does NOT touch the PHP constraint — keeping the two changes from colliding on `composer.json`.

## Goals / Non-Goals

**Goals:**
- Reusable money value objects under `App\Platform\Money` that make the substrate's payload discipline unrepresentable to violate: `Money` (integer minor units + `Currency`, no float path), `Currency` (ISO 4217 code + exponent, launch set, fail-closed), `FxRate` (exact decimal string), `DualCurrencyAmount` (the D18 dual-record representation), and a `MoneyCast` so F2 columns store money without precision loss.
- An i18n foundation: the six-locale supported registry with English fallback, `lang/` scaffolding, the `TranslatableText` i18n-keyed-JSON primitive + cast (per-attribute English fallback, app-layer locale validation), and `welcome.blade.php` cleaned of hardcoded copy.
- Feature-flag infrastructure (Laravel Pennant) with the EXT-1 NFT/on-chain flag default-OFF and a reusable accessor.
- The reusable `ActorContext` resolver seam — the one canonical way to obtain `actor_role`/`actor_id`, defaulting to `system` until the auth ADR.
- The forward-references (docs + the recorder docblock + GUIDE) updated to present tense; `CONTEXT.md` glossary extended.

**Non-Goals (explicit gate-stops and deferrals):**
- **Operator/producer/customer actor resolution** (mapping an authenticated principal to `actor_role`) → identity/auth ADR + Module K. `ActorContext` returns `system` and reads no auth state, so it does **not** step through the auth gate.
- **The full DEC-127 request-locale resolution chain** (`Accept-Language` → sticky cookie → English, manual switcher, Bottle-Page render) → Module B / frontend. This change ships the registry + the per-attribute fallback primitive, not the request-time pipeline.
- **The consumer/producer frontend stack** → TanStack ADR (Module S gate). `welcome.blade.php` becomes a minimal localized placeholder with no routing and no stack choice.
- **Module E FX policy** (snapshot time, buffer %, refresh cadence, per-leg lock moments — DEC-038/DEC-169) → Module E. `DualCurrencyAmount` is pure representation.
- **Flipping EXT-1 on** (the NFT/on-chain workstream) → external blockchain-expert review; the flag ships OFF.
- **Any module usage of these primitives** (Club Credit, Catalog translatable columns, Offer/Allocation prices, Module E financial-event payloads) → F2+.
- **The `php ^8.3`→`^8.5` composer bump** → `substrate-hardening`.
- Reopening anything the substrate ADR, the DB ADR, the stack ADR or the spec decided.

## Decisions

### D1 — Platform sub-namespaces; NO boundary amendment needed

The new code lives under the existing `App\Platform` root:

```
app/Platform/
├── Money/
│   ├── Currency.php              # enum or VO: ISO 4217 code + minor-unit exponent (launch set, fail-closed)
│   ├── Money.php                 # immutable VO: int minorUnits + Currency; no float path; minor-units arithmetic
│   ├── FxRate.php                # VO: exact decimal string, never float
│   ├── DualCurrencyAmount.php    # VO: customer Money + EUR Money + FxRate + rate date → DEC-169 payload shape
│   └── MoneyCast.php             # Eloquent cast: two columns (minor units + currency) ↔ Money
├── I18n/
│   ├── SupportedLocale.php       # the single-source-of-truth six-locale registry (enum) + fallback
│   ├── TranslatableText.php      # VO: {locale: text}; resolve(locale) with per-attribute English fallback
│   └── TranslatableTextCast.php  # Eloquent cast: i18n-keyed JSON column ↔ TranslatableText
├── Features/
│   └── Features.php              # defined feature names + reusable accessor (wraps Pennant Feature::active)
└── Events/
    └── ActorContext.php          # the actor_role/actor_id resolver seam (System default; runAs override)
```

`tests/Architecture/ModuleBoundariesTest.php` already lists `'App\Platform'` in `$platformNamespaces`, and Pest arch matches **by name-prefix** (verified at authoring) — so `App\Platform\Money`, `\I18n`, `\Features` are covered automatically and the platform-never-imports-modules law holds for them with **no amendment and no new red-proof** (the F1 2/3 red-proof already proved non-vacuity for the `App\Platform` prefix). This is a genuine simplification vs. the prior change; tasks only need the new code to not import `App\Modules` (trivially true and already enforced). `ActorContext` sits in `App\Platform\Events` next to `ActorRole` (both are envelope-actor concerns; both recorders already live there).

### D2 — Money: owned value objects, no new dependency

`Money` is an immutable VO of `int $minorUnits` + `Currency`. There is **no float-accepting path** — construction is `Money::of(int $minorUnits, Currency $currency)` (name a loop detail); a `fromMajorUnits(int, int, Currency)` style helper, if added, still takes integers only. Arithmetic (`plus`/`minus`/`negate`) works on minor units and throws on currency mismatch. Equality is by (minorUnits, currency). Serialisation yields `['minor_units' => int, 'currency' => 'EUR']` for payloads. Negative values are valid (Club Credit, refunds, reversals).

`Currency` carries the ISO 4217 code + minor-unit exponent. Realised as a string-backed enum or a small VO with a private supported-map: EUR (base, exp 2), USD (2), GBP (2), CHF (2), JPY (0). Unknown codes are **rejected (fail-closed)** — assuming exp 2 for an unlisted currency would silently mis-scale JPY-like currencies. Adding a currency = one entry in the map (config-not-migration ethos).

**Alternative considered — a money library (`brick/money`, `moneyphp/money`):** rejected. Our launch needs are minimal (hold, compare, add same-currency, serialise), the substrate ADR set a no-new-heavyweight-deps posture, and adding a money dependency would itself require an ADR. Owned VOs keep the dep count at one (Pennant) and the behaviour exactly what the invariants demand. Revisit via ADR only if real arithmetic needs (allocation/rounding splits) emerge.

### D3 — FxRate + DualCurrencyAmount: decimal-string, policy-free

`FxRate` wraps a validated decimal string and never exposes a float — the typed enforcement of the substrate's "FX rates as decimal strings, never floats" contract (which the F1 2/3 envelope scenario already pins). `DualCurrencyAmount` bundles customer `Money` + EUR `Money` + `FxRate` + rate-date and serialises to the exact DEC-169 shape (`amount`, `currency`, `eur_equivalent_amount`, `fx_rate`, `fx_rate_date`). **Landmine:** it is pure representation. It must NOT encode which leg locks when, snapshot/buffer mechanics, or any FX-policy — that is Module E (DEC-169) and belongs to a later change. The EUR leg's currency is asserted to be EUR at construction. It preserves the locked rate verbatim (refunds reuse it; the VO never re-derives).

### D4 — i18n: registry, lang scaffolding, translatable primitive

- **Supported-locale registry** (`config/i18n.php` + a `SupportedLocale` enum as the typed anchor): the six locales `en, it, fr, de, ja, zh_Hans` with `en` as fallback. Identifiers follow the spec's own AC-0-XM-4 wording ("JA", "zh-Hans") in Laravel's underscore form. `config/app.php` already sets `locale=en` / `fallback_locale=en` — left as-is.
- **`lang/` scaffolding**: English authored; the other five MAY stagger (AC-0-XM-4 allows partial coverage). Use whichever of PHP-array files (`lang/{locale}/*.php`) or JSON (`lang/{locale}.json`) Laravel 13 resolves cleanly — verify the installed convention in vendor before choosing; document the choice.
- **`TranslatableText`** (VO) + **`TranslatableTextCast`** (Eloquent cast): holds `{locale: text}`; `resolve(?string $locale)` returns the requested locale's text or the English value for that attribute when absent (per-attribute fallback — DEC-127 item 4, never whole-object). Locale keys are validated against the registry at the application layer (DEC-064 — the column stays schema-less JSON). Round-trips to/from JSON without loss. This is the primitive Module 0 PIM (F2) attaches to translatable columns; **this change does not attach it to any column** (no module entities yet).
- **`welcome.blade.php`** → minimal localized placeholder: replace the 72KB default Laravel marketing page with a small holding page whose visible strings all use `__()` keys (added to `lang/`). No routing, no SPA, no frontend-stack choice (TanStack gate). **Landmine:** do not "fully localize" the default page into six languages — that is wasted effort on a page the TanStack frontend will replace; remediate to a minimal placeholder.

### D5 — Feature flags: Laravel Pennant (first-party, latest stable)

`composer require laravel/pennant` resolves to `^1.23` (latest stable; verified resolvable against Laravel v13.15.0 via `--dry-run` at authoring 2026-06-13 — note the dry-run still edits `composer.json`, so the real install happens in-loop and the lockfile is committed then). Pennant is a first-party Laravel package: framework-conventional, **no standalone dependency ADR required** (same posture as the stack ADR's Laravel Boost adoption). Publish `config/pennant.php`; run its `features` table migration (Postgres-truthful / SQLite-compatible — verify the published migration is SQLite-clean, adjust per the DB ADR's documented-fallback path if not).

Define the **EXT-1** NFT/on-chain feature returning `false` by default (a global feature — `Feature::define('nft-on-chain', fn () => false)`; verify the Pennant global-feature + scope API in vendor). Wrap resolution in a reusable `Features` accessor (a typed constant/enum for the name + a thin `active()` helper) so call-sites never pass a magic string. Document the NS-path-as-universal-fallback convention.

**Alternative considered — a plain config boolean:** rejected. The Workplan calls for feature-flag *infrastructure* (carry i), and ops must be able to flip the gate without a deploy once the on-chain workstream is reviewed — Pennant's DB-backed store gives that; a config bool does not.

### D6 — ActorContext: the gate-safe actor seam

`ActorContext` resolves `(ActorRole, ?int $actorId)` for the current context. Launch behaviour: returns `(ActorRole::System, null)` for console/queue/unauthenticated contexts; `runAs(ActorRole $role, ?int $actorId, callable $fn)` (or a settable scope) applies a role for a callable's duration and restores afterward. **Landmine:** it reads NO authentication state and imports no auth/Filament/module code — that keeps it on the safe side of the identity/auth ADR gate (which fires before Module K). It is the single seam the auth ADR will later wire to the authenticated principal, with no call-site churn. Wire the existing platform demo (and any seeder) to obtain its role via `ActorContext` instead of the literal `ActorRole::System`, demonstrating the seam without changing behaviour. The `ActorRole` enum and the NOT-NULL envelope column are untouched (they exist).

### D7 — Docs & forward-reference cleanup

Update the live forward-references now that the VOs exist: `docs/event-substrate.md` (the "F1 3/3 value objects will make…" line → present tense, pointing at `App\Platform\Money` as the payload-building path) and the `DomainEventRecorder` docblock (same line). Refresh the `GUIDE.md` F1 status line to mark 3/3 authored/done. Extend `CONTEXT.md` with the resolved glossary terms (Money, Currency, FX Rate, Dual-Currency Amount, Supported locale, Translatable text, Feature flag / EXT-1, Actor context) in the existing house format. **Do not** rewrite the two archived change proposals — they correctly recorded F1 3/3 as "next" when written; their pointer is fulfilled by this change being archived in turn. Doc-pin tests follow the sibling idiom where a doc is behaviour-bearing.

## Risks / Trade-offs

- **[Vendor APIs written from memory]** Pennant's define/active/global-feature API + its published migration, Laravel 13's `CastsAttributes` custom-cast contract, the localization file convention (`lang/*.php` vs JSON) and `__()`/`trans()` fallback behaviour, the SQLite-cleanliness of Pennant's migration. → The standing lesson applies: **verify each in `vendor/` before writing** (lessons.md). Each task names the API to verify.
- **[Pennant migration not SQLite-clean]** A published vendor migration could use a PG-only construct. → Verify on `:memory:` first; if it breaks, wrap per the DB ADR's documented-fallback path and keep PG truth, recording the resolution in `progress.md`.
- **[Fail-closed currency set rejects a code a module needs]** A future module needing a sixth currency will hit a rejection. → That is the intended fail-closed behaviour (a wrong exponent is a money bug); the fix is a one-line registry addition, documented on `Currency`. DEC-037 fixes the launch set at five.
- **[DualCurrencyAmount drifting into Module-E policy]** The composite is the item closest to a module boundary. → Pure-representation is a tested property (no rate is derived; no snapshot/lock logic); the requirement and D3 state the boundary; if an iteration is tempted to add policy it escalates rather than crossing into Module E.
- **[welcome placeholder pre-empting the frontend ADR]** Over-building the placeholder could imply a frontend stack. → Minimal holding page only: keyed strings, no routing, no SPA, no asset pipeline beyond what exists. The TanStack decision stays untouched.
- **[ActorContext crossing the auth gate]** Reading auth state would step through the identity/auth ADR. → It reads none and returns `system`; the "ignores authentication" scenario pins this. Operator wiring is explicitly the auth ADR's.
- **[Composer churn / unobserved lockfile]** Adding Pennant changes `composer.json`/`composer.lock` (unlike F1 2/3, which forbade churn). → Expected and bounded to Pennant + transitive needs; pin `^1.23` (latest stable), commit the lockfile, record the exact installed version in `docs/development.md` + `progress.md` (stack-ADR convention); do NOT touch the PHP constraint (substrate-hardening owns it).
- **[Money VO ergonomics vs. a library]** Owned VOs mean we maintain arithmetic ourselves. → Scope is deliberately minimal (hold/compare/add-same-currency/serialise); the library option stays open behind an ADR if real rounding/allocation needs arrive.

## Migration Plan

Additive. New code under `app/Platform/{Money,I18n,Features,Events}`; new `config/i18n.php` + published `config/pennant.php`; new `lang/**`; one new migration (Pennant `features` table); `welcome.blade.php` replaced; docs touched. One dependency added (`laravel/pennant`). Deploy = merge (no production environment yet). Rollback = `git revert` of the merge + drop the `features` table; removing Pennant is `composer remove` (the only non-owned piece). No existing table or behaviour changes; the substrate, the module skeletons and the arch suite are untouched save the demo's role-via-seam refactor (behaviour-preserving).

## Open Questions

Three forks the spec leaves to realization, decided by default below and surfaced for founder veto at the approval gate — none blocks authoring:

1. **`DualCurrencyAmount` in this slice?** Default: **yes** — the Workplan calls the dual-record pattern a Phase-1 "full pattern" and the founder's brief ties Money to "D18 dual-record FX". It ships as a pure representation; its first consumer is Module E (F6). Alternative: ship only `Money`/`Currency`/`FxRate` now and defer the composite to Module E.
2. **`welcome.blade.php`:** Default: **minimal localized placeholder** (debt S3 remediated, no frontend-stack decision). Alternative: fully localize the existing default page (rejected as wasted effort pre-TanStack).
3. **Carry (iii) scope:** Default: **the one gate-safe `ActorContext` seam** (the enum + envelope already exist from F1 2/3). Alternative: treat carry (iii) as already-satisfied and drop the resolver from this change.

If an implementation iteration finds a contradiction between this design and an ADR or the spec, the ADR/spec wins and the iteration escalates (`HUMAN_NEEDED`) rather than reinterpreting.
