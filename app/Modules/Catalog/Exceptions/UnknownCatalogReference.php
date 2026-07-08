<?php

namespace App\Modules\Catalog\Exceptions;

use RuntimeException;

/**
 * Raised when a catalog write names a within-module entity by id and that entity does not exist
 * (catalog-module-0-completeness-sweep, design D6; product-catalog — Requirement: Layer-1 Case-Configuration
 * Whitelist).
 *
 * The referenced ids are FK-backed, so a bad id would ALREADY be refused — by the database, as a raw
 * `QueryException` carrying a constraint name and no operator-facing meaning. This exception exists to convert
 * that structural refusal into a DOMAIN rejection: checked inside the write's transaction, before any row is
 * touched, so an operator (and the console kit's outcome path) reads "these ids do not exist" rather than a
 * driver error. The FK stays as the structural backstop — the two are belt and braces, and neither is
 * redundant: the pre-check owns the message, the constraint owns the integrity.
 *
 * The deliberate contrast is with `CreateCompositeSku`, which lets its join's foreign key speak alone: no
 * operator ever types a constituent id there (the console prefills a select), whereas whitelist maintenance
 * carries a Format id AND an arbitrary-length Case-Configuration set through the same call — a set in which a
 * single stale id must name ITSELF, not the whole write, in the rejection.
 *
 * ONE parameterized exception serves every reference kind, exactly as {@see IllegalContentEdit} and
 * {@see IllegalLifecycleTransition} serve every entity: the fact ("this id resolves to nothing") is uniform, so
 * the entity name is a factory parameter. The reason is localized through Laravel's translator (CLAUDE.md
 * invariant 12) from the `reference` group of `lang/en/catalog.php`. Both interpolations are PII-free: `$entity`
 * is an entity-type label (e.g. `CaseConfiguration`) and `$ids` a list of surrogate keys. `(string)` coerces the
 * translator return (typed `mixed` by Larastan) to the RuntimeException message contract.
 */
class UnknownCatalogReference extends RuntimeException
{
    /**
     * @param  list<int>  $ids  the ids that resolved to nothing — the offending subset, never the whole input
     */
    public static function forIds(string $entity, array $ids): self
    {
        return new self((string) __('catalog.reference.unknown_reference', [
            'entity' => $entity,
            'ids' => implode(', ', $ids),
        ]));
    }
}
