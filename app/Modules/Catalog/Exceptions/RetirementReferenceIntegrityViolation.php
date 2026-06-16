<?php

namespace App\Modules\Catalog\Exceptions;

use App\Modules\Catalog\Actions\RetireProductMasterCascade;
use RuntimeException;

/**
 * Raised when a SINGLE-entity `active → retired` retire is blocked by the within-catalog reference-integrity
 * guard — the entity is still referenced by an `active` terminal sellable object that has not completed
 * (catalog-lifecycle-approval, design D8; product-catalog — Requirement: Retirement Cascade and Reference
 * Integrity; Module 0 PRD § 4.6, BR-Lifecycle-5 — the within-catalog subset). Scoped per
 * `decisions/2026-06-16-catalog-retirement-reference-integrity-scope.md` (Option B) to the TERMINAL SELLABLE
 * EDGE only: a Product Reference referenced by an `active` Sellable / Composite SKU, or a Case Configuration
 * referenced by an `active` Sellable SKU. Retiring the referenced entity out from under a still-`active` SKU
 * would silently orphan something currently sellable, so the retire is rejected at the workflow level and the
 * open references are SURFACED — the operator closes them (or retires the whole tree via the operator-driven
 * {@see RetireProductMasterCascade}) and then the retire proceeds.
 *
 * A HIERARCHY PARENT is deliberately NOT guarded by this exception: a Product Master with `active` Variants, or
 * a Product Variant with `active` Product References, is single-retirable and PRESERVES those children (they
 * stay `active`; only new activation under the now-`retired` parent is prevented — § 4.5 / BR-Lifecycle-4). So
 * only `RetireProductReference` and `RetireCaseConfiguration` raise this; `RetireProductMaster` /
 * `RetireProductVariant` stay guard-free. The CROSS-MODULE downstream-reference leg (active Allocations, issued
 * vouchers, in-flight orders, SKUs on live Offers) is a documented Phase-3 seam — those referencers do not
 * exist yet, so this guard covers within-catalog references only and the Phase-3 referencer changes extend it.
 *
 * The copy (the `retirement` group of `lang/en/catalog.php`; CLAUDE.md invariant 12 — no hardcoded
 * user-facing strings) names the `:entity` type label and surfaces `:references` — the open referencers as
 * entity-type + id tokens (e.g. `SellableSku#5, CompositeSku#9`) — never a party name or personal data
 * (PII-free; invariant 10). `(string)` coerces the translator return (typed `mixed` by Larastan) to the
 * RuntimeException message contract.
 */
class RetirementReferenceIntegrityViolation extends RuntimeException
{
    /**
     * @param  array<int, string>  $openReferences  the open `active` referencers as entity-type + id tokens (non-empty)
     */
    public static function blockedByActiveReferences(string $entity, array $openReferences): self
    {
        return new self((string) __('catalog.retirement.blocked_by_active_references', [
            'entity' => $entity,
            'references' => implode(', ', $openReferences),
        ]));
    }
}
