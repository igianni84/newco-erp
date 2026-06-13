<?php

namespace App\Platform\Features;

/**
 * The single source of truth for the platform's defined feature-flag names
 * (foundations-money-i18n-flags, design D5; feature-flags capability — Requirement:
 * Feature-Flag Infrastructure / NFT/On-Chain Decoupling Flag).
 *
 * Flags are resolved through {@see Features}, which takes a case of this enum — so a
 * call-site never passes a magic string and a misspelled or undefined flag name is a
 * type error (or `tryFrom()` returns null). The cases ARE the defined set: the reusable
 * accessor registers every case with Pennant, so adding a flag is one case here plus its
 * `defaultState()` arm — no migration (the `features` table is schema-stable).
 *
 * - case name    = PascalCase symbol (App\Platform vocabulary)
 * - backing value = the Pennant feature name (the persisted token)
 */
enum FeatureFlag: string
{
    /**
     * EXT-1 — the re-scoped NFT/on-chain gate. OFF at launch; gates NFT mint/burn, the
     * custodial wallet, on-chain recovery and the Bottle-Page chain-link. It does NOT
     * gate the per-bottle serialization workflow (which ships launch-ready). When off,
     * the non-serialized (NS) path is the universal fallback. See docs/feature-flags.md.
     */
    case NftOnChain = 'nft-on-chain';

    /**
     * The flag's launch default — the deterministic state it resolves to until an
     * operational value is stored. Exhaustive (no `default` arm): a new flag cannot
     * compile until its launch default is declared here.
     */
    public function defaultState(): bool
    {
        return match ($this) {
            self::NftOnChain => false,
        };
    }
}
