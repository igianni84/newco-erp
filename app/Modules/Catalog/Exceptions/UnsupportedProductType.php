<?php

namespace App\Modules\Catalog\Exceptions;

use RuntimeException;

/**
 * Raised when a Product Master creation is requested with a Product Type other than `WINE`
 * (catalog-product-spine, design D2; product-catalog — Requirement: Category-Neutral Product Type). At
 * launch `WINE` is the only supported type (AC-0-XM-9); constructing a Master of any other type is rejected
 * fail-closed at the creation boundary, never silently accepted. This is the application-layer guard; the
 * `product_type` CHECK constraint (PostgreSQL) is the DB-level backstop.
 *
 * The reason is localized through Laravel's translator (CLAUDE.md invariant 12); the English baseline lives
 * in `lang/en/catalog.php`. `(string)` coerces the translator return (typed `mixed` by Larastan) to the
 * RuntimeException message contract.
 */
class UnsupportedProductType extends RuntimeException
{
    public static function forToken(string $productType): self
    {
        return new self((string) __('catalog.product_master.unsupported_product_type', [
            'type' => $productType,
        ]));
    }
}
