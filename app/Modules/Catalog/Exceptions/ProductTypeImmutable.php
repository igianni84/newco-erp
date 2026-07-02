<?php

namespace App\Modules\Catalog\Exceptions;

use App\Modules\Catalog\Models\ProductMaster;
use RuntimeException;

/**
 * Raised when an attempt is made to change a Product Master's `product_type` AFTER creation
 * (catalog — BR-Identity-5 / canon MVP-DEC-023: Product Type is fixed at creation, never a type-edit; ADR
 * decisions/2026-07-02-adopt-dec-023-product-type-immutable.md). A Master's type selects its per-type
 * attribute set, its variant-defining dimension and its identity-uniqueness key (design D2), so re-typing a
 * live Master would orphan its attribute set and invalidate its identity — the canon remedy is retire +
 * re-register under the required type, never an in-place edit.
 *
 * Enforced at the model's `updating` chokepoint ({@see ProductMaster::booted()}):
 * the sole guard that catches EVERY mutation path — there is no update Action and the Filament resource is
 * read-only, but `product_type` is a real mutable column with `$guarded = []`. Contrast Module K's
 * `party_type`, immutable BY CONSTRUCTION (distinct per-subtype tables, ADR 2026-06-15-party-type-marker-on-subtype).
 *
 * The reason is localized through Laravel's translator (CLAUDE.md invariant 12); the English baseline lives
 * in `lang/en/catalog.php`. `(string)` coerces the translator return (typed `mixed` by Larastan) to the
 * RuntimeException message contract.
 */
class ProductTypeImmutable extends RuntimeException
{
    public static function forMaster(int|string $masterId): self
    {
        return new self((string) __('catalog.product_master.immutable_product_type', [
            'id' => $masterId,
        ]));
    }
}
