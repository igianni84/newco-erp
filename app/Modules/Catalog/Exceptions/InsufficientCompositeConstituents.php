<?php

namespace App\Modules\Catalog\Exceptions;

use RuntimeException;

/**
 * Raised when a Composite SKU creation is requested with fewer than two distinct constituent Product References
 * (catalog-product-spine, design D9; product-catalog — Requirement: Composite SKU; Module 0 PRD §3.8, §13.5
 * BR-SKU-2). A Composite SKU is a curated bundle of N ≥ 2 constituents; a request that does not meet that floor
 * is rejected at the creation boundary, never silently persisted as a degenerate one- or zero-element bundle.
 * Constituents are an ordered SET (the join's unique `(composite, PR)` makes a PR appear at most once per
 * composite), so the count is taken over the DISTINCT constituent ids — this is a cross-row count rule held in
 * the action, the same shape as the Master's dedup (and unlike a single-table DB constraint).
 *
 * This is deliberately the ONLY admissibility guard: the catalog is producer-agnostic about constituents
 * (design D9 / BR-SKU-5) — it does NOT validate producer composition; that is a Module S Offer-publication rule.
 *
 * The reason is localized through Laravel's translator (CLAUDE.md invariant 12 — no hardcoded user-facing
 * strings): the English baseline lives in `lang/en/catalog.php`, other locales fall back per-key. `(string)`
 * coerces the translator return (typed `mixed` by Larastan) to the RuntimeException message contract.
 */
class InsufficientCompositeConstituents extends RuntimeException
{
    public static function forCount(int $count): self
    {
        return new self((string) __('catalog.composite_sku.insufficient_constituents', [
            'count' => $count,
        ]));
    }
}
