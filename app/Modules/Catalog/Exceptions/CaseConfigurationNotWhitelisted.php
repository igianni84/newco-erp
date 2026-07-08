<?php

namespace App\Modules\Catalog\Exceptions;

use RuntimeException;

/**
 * Raised when a Sellable SKU's `reviewed → active` transition is blocked by the Layer-1 case-configuration
 * whitelist — the SKU's Case Configuration is not admitted for its (Product Variant, Format) pair
 * (catalog-module-0-completeness-sweep, design D6, risk R10; product-catalog — Requirement: Layer-1
 * Case-Configuration Whitelist; Module 0 PRD § 7.1 + § 4.5, AC-0-J-13).
 *
 * A SIBLING of {@see ActivationCascadeViolation}, never a case of it. Both are hard activation gates on the
 * same transition, and the distinction is the whole content of Layer 1: the cascade asks whether a referenced
 * entity is READY (`active`), the whitelist asks whether this packaging is POSSIBLE for this product in this
 * format at all. A perfectly `active` Case Configuration can fail this gate, and a caller catching the two
 * types apart learns which of the two questions it answered wrong — so they are two classes, exactly as the
 * cross-module {@see ProducerActivationGateViolation} stands apart from the within-module cascade.
 *
 * The gate is consulted ONLY at activation, and only when the pair holds a NON-EMPTY whitelist: an empty pair
 * is permissive (§ 7.1's default — absence admits, presence narrows), so this exception never speaks for a
 * whitelist that does not exist. Nor does it reach backwards: removing an admitted Case Configuration from a
 * pair blocks the NEXT activation and leaves every already-`active` Sellable SKU standing (§ 4.5's
 * retirement-cascade semantics, risk R10). It is therefore raised on exactly one surface, which is why it
 * carries ONE factory where `ActivationCascadeViolation` — an invariant reachable from both activation and
 * composition-edit — carries two.
 *
 * The copy (the `gate` group of `lang/en/catalog.php`; CLAUDE.md invariant 12 — no hardcoded user-facing
 * strings) names only the violated rule and the `:entity` type label. The offending Case Configuration and the
 * pair are NOT interpolated: both are already legible to the operator (the SKU they tried to activate carries
 * the one, its Product Reference the other) and the audit trail records the whitelist's before/after sets.
 * PII-free (invariant 10). `(string)` coerces the translator return (typed `mixed` by Larastan) to the
 * RuntimeException message contract.
 */
class CaseConfigurationNotWhitelisted extends RuntimeException
{
    /**
     * @param  string  $entity  the activating child's canonical entity-type label (`SellableSku`) for the rejection copy
     */
    public static function notAdmittedForPair(string $entity): self
    {
        return new self((string) __('catalog.gate.case_configuration_not_whitelisted', ['entity' => $entity]));
    }
}
