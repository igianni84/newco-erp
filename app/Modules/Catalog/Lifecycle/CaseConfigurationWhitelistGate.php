<?php

namespace App\Modules\Catalog\Lifecycle;

use App\Modules\Catalog\Exceptions\CaseConfigurationNotWhitelisted;
use App\Modules\Catalog\Models\VariantCaseWhitelistEntry;

/**
 * The Layer-1 case-configuration whitelist gate — the packaging precondition on a Sellable SKU's
 * `reviewed → active` transition (catalog-module-0-completeness-sweep, design D6, risk R10; product-catalog —
 * Requirement: Layer-1 Case-Configuration Whitelist; Module 0 PRD § 7.1 + § 4.5, AC-0-J-13). The
 * `ActivateSellableSku` action passes {@see assertCaseConfigurationAdmitted()} as the LAST conjunct of its
 * activation-gate closure, which the shared {@see LifecycleTransition} mechanism evaluates after the
 * approval-governance guard and BEFORE the state write, inside the transition's transaction; a breach throws
 * {@see CaseConfigurationNotWhitelisted} and the whole transition rolls back, so the SKU stays `reviewed` and
 * records no `SellableSKUActivated`.
 *
 * PERMISSIVE by default, where its two sibling gates are fail-closed. {@see ActivationCascadeGate} and
 * {@see ProducerActivationGate} reject on an absent parent, because a missing referent is a broken reference.
 * A pair with NO whitelist rows is not a broken reference: § 7.1's whitelist is an OPTIONAL narrowing —
 * "presence narrows, absence admits" — so zero rows means the operator has stated nothing about this pair, and
 * stating nothing cannot block anything. Only a NON-EMPTY admitted set has an opinion, and then the SKU's Case
 * Configuration must be in it. This is the one gate in Module 0 whose empty read means *pass*.
 *
 * The pair, not the Variant, is the unit (design D6; § 3.3's "this product, in this format"): the SKU's
 * (Product Variant, Format) pair is resolved by its Product Reference — the Action owns that within-module
 * read and hands the two ids here, mirroring how {@see ActivationCascadeGate} receives an already-loaded
 * parent. Narrowing one Format's set therefore leaves the same Variant's other formats permissive.
 *
 * CONSULTED ONLY AT ACTIVATION (risk R10, § 4.5's retirement-cascade semantics). There is no sweep, no
 * revalidation, and no path from `SetVariantCaseWhitelist` (the whitelist's sole writer) to an existing SKU:
 * removing an admitted Case Configuration blocks the NEXT activation against it and leaves every already-`active`
 * Sellable SKU exactly as it stands — state, `version`, audit trail and event log all untouched. Like its
 * siblings the read is intentionally lock-free: the gate blocks NEW activation, it never cascade-retires an
 * in-flight one.
 *
 * Layer 1 catalogs POSSIBILITY, never breakability (AC-0-XM-11 / BR-RefData-2): this gate reads admission and
 * nothing else, because the pivot exposes nothing else. The Layer-2 upper-bound check at allocation creation
 * (Module A, § 7.2) and the rule that Layer-1 reductions never retroactively invalidate Layer-2 declarations
 * on already-active allocations remain DOCUMENTED SEAMS — Modules A and S do not exist yet.
 */
class CaseConfigurationWhitelistGate
{
    /**
     * Assert that the activating child's Case Configuration is admitted for its (Product Variant, Format) pair,
     * else reject the activation. A pair with no whitelist rows admits everything.
     *
     * @param  int  $productVariantId  the Variant half of the pair, resolved through the child's Product Reference
     * @param  int  $formatId  the Format half of the pair, resolved through the child's Product Reference
     * @param  int  $caseConfigurationId  the packaging the activating child references
     * @param  string  $entity  the child's canonical entity-type label (`SellableSku`) for the rejection copy
     *
     * @throws CaseConfigurationNotWhitelisted when the pair has a non-empty whitelist that excludes the Case Configuration
     */
    public function assertCaseConfigurationAdmitted(int $productVariantId, int $formatId, int $caseConfigurationId, string $entity): void
    {
        // ONE query answers both questions the gate asks — "does this pair narrow anything?" and "is this
        // packaging in the narrowing?" — because an admitted set is small by construction (the Case
        // Configurations one product can be packaged in, in one format). Hydrating the rows rather than
        // `pluck()`ing them earns the `int` from the model's cast; `pluck` is `mixed` to static analysis.
        $admitted = VariantCaseWhitelistEntry::query()
            ->where('product_variant_id', $productVariantId)
            ->where('format_id', $formatId)
            ->get()
            ->map(fn (VariantCaseWhitelistEntry $entry): int => $entry->case_configuration_id)
            ->all();

        // The permissive default: the operator has stated nothing about this pair, so nothing is excluded.
        if ($admitted === []) {
            return;
        }

        if (! in_array($caseConfigurationId, $admitted, true)) {
            throw CaseConfigurationNotWhitelisted::notAdmittedForPair($entity);
        }
    }
}
