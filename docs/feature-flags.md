# Feature flags

How build-incomplete or commercially-gated surfaces are toggled without a code change.
Scope at launch: the feature-flag infrastructure (Laravel Pennant), the typed flag
registry + reusable accessor, and the single launch flag — **EXT-1** (NFT/on-chain),
shipped OFF. The operator surface that flips flags is a later change. Authority:
`spec/05-release/Build_Workplan_v0.3-MVP.md` § 2 Phase 1 (MVP carry (i)),
`spec/02-prd/Architecture_v0.3-MVP.md` § 4 / § 6.1 / § 8.2 (EXT-1 / D12),
`decisions/2026-06-11-stack-versions-and-filament-ai-tooling.md` (framework-conventional
first-party packages), CLAUDE.md (D12 — NFT/on-chain decoupled behind a feature flag).

## Infrastructure

Laravel Pennant (first-party; exact version pinned in `docs/development.md`) backs feature
flags. The launch store is the database-backed `features` table (`config/pennant.php`), so
a flag's value can be changed operationally without a deploy. Values are persisted per
scope; the launch flags are **global** — their resolver ignores scope, so they read the
same for every caller until an operational override is stored.

## The typed registry + accessor (no magic strings)

Two small classes under `App\Platform\Features`:

- **`FeatureFlag`** — a `string`-backed enum, the single source of truth for defined flag
  names (the cases ARE the set). Each case carries its launch default via an exhaustive
  `defaultState()` match, so a new flag cannot compile until its default is declared.
- **`Features`** — the reusable accessor. `Features::define()` registers every
  `FeatureFlag` case with Pennant (called once from `AppServiceProvider::boot()`);
  `Features::active(FeatureFlag $flag): bool` resolves a flag.

Call-sites resolve a flag through the typed accessor and never pass a string:

```php
use App\Platform\Features\FeatureFlag;
use App\Platform\Features\Features;

if (Features::active(FeatureFlag::NftOnChain)) {
    // on-chain surface …
}
```

Because the accessor takes a `FeatureFlag`, a misspelled or undefined flag name is a type
error (and `FeatureFlag::tryFrom('…')` returns `null` for any name outside the set) — a
magic-string flag can never be resolved.

**Adding a flag** is one enum case plus its `defaultState()` arm; `Features::define()`
picks it up automatically. No migration — the `features` table is schema-stable.

## EXT-1 — the NFT/on-chain gate (OFF at launch)

`FeatureFlag::NftOnChain` (`'nft-on-chain'`) is the re-scoped **EXT-1** gate. It is **OFF
by default at launch** and gates the on-chain surfaces:

- NFT mint/burn
- the custodial wallet
- on-chain recovery
- the Bottle-Page chain-link

It is the **single named gate** for those surfaces — code decides whether an on-chain
surface is active by consulting this one flag through the accessor (and at launch always
receives off). Turning it on is gated on an external blockchain-expert review and is out of
scope here (DECOUPLE ≠ DEFER — D12).

### What EXT-1 does NOT gate

The per-bottle **serialization workflow** — NFC tag application + serial capture + the
`SerializedBottle` ledger record + Logilize integration — **ships launch-ready and has no
dependency on this flag**. Serialization is independent of the on-chain workstream; there
is deliberately no feature-flag name for it.

### The NS path is the universal fallback

When EXT-1 is off (the launch state), the **non-serialized (NS) path is the universal
fallback**: every downstream surface degrades gracefully to NS. The NS sub-pool ATP is the
load-bearing floor (Architecture § 8.2) — the launch floor does not depend on the decoupled
NFT workstream. The platform convention: an off on-chain flag never blocks a sale, a
shipment or a custody operation; it routes to the NS fallback.
