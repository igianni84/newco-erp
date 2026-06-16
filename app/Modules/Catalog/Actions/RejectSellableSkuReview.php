<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Lifecycle\LifecycleTransition;
use App\Modules\Catalog\Models\SellableSku;

/**
 * Rejects a Sellable SKU under review (§ 4.3) through the shared {@see LifecycleTransition} mechanism
 * (catalog-lifecycle-approval task 4.5; design D5; product-catalog — Requirement: Approval Governance).
 *
 * A reviewer or approver rejects a SKU in `reviewed`: the SKU STAYS in `reviewed` (there is no revert to
 * `draft`), one `audit_records` row captures the actor, the `decision: rejected` and the `$notes`, and NO
 * domain event is recorded. The SKU is then edited in place and the approval flow continues — the append-only
 * audit trail preserves the full rejection history, and "rejection-pending" is DERIVED from the latest
 * governance audit action (no schema flag) so a later approval clears it. Operator-floored (a `system`/null
 * actor cannot reject) and from-state guarded (a reject on a non-`reviewed` SKU throws
 * {@see IllegalLifecycleTransition} and writes nothing). A thin per-entity wrapper — the entity label
 * `SellableSku` matches the domain-event `entity_type`; the model stays persistence-only.
 */
class RejectSellableSkuReview
{
    public function __construct(private readonly LifecycleTransition $lifecycle) {}

    /**
     * @throws IllegalLifecycleTransition when the SKU is not in `reviewed`
     * @throws ApprovalGovernanceViolation when there is no authenticated operator principal
     */
    public function handle(SellableSku $sellableSku, string $notes): SellableSku
    {
        return $this->lifecycle->reject($sellableSku, 'SellableSku', $notes);
    }
}
