<?php

namespace App\Modules\Catalog\Exceptions;

use RuntimeException;

/**
 * Raised when a Product Master creation collides with the §13.1 BR-Identity-1 uniqueness key
 * (catalog-product-spine, design D6; product-catalog — Requirement: Product Master). For `WINE` the
 * type-defined identity key is `producer + product name + appellation`; a creation whose key matches an
 * existing NON-RETIRED Master is rejected (deduplication enforced at creation, on the manual baseline
 * path — AC-0-J-3).
 *
 * The reason is localized through Laravel's translator (CLAUDE.md invariant 12 — no hardcoded user-facing
 * strings): the English baseline lives in `lang/en/catalog.php`, other locales fall back per-key. The
 * identity values placed in the message (name, appellation, producer id) are operator-facing identity, not
 * PII. `(string)` coerces the translator return (typed `mixed` by Larastan) to the RuntimeException
 * message contract.
 */
class DuplicateProductMasterIdentity extends RuntimeException
{
    public static function forWine(int $producerId, string $name, string $appellation): self
    {
        return new self((string) __('catalog.product_master.duplicate_identity', [
            'name' => $name,
            'appellation' => $appellation,
            'producer' => $producerId,
        ]));
    }
}
