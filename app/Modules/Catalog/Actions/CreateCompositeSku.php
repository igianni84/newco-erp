<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\CompositeSKUCreated;
use App\Modules\Catalog\Exceptions\InsufficientCompositeConstituents;
use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\Module;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Composite SKU — a curated bundle of N ≥ 2 ordered constituent Product References — and records its
 * {@see CompositeSKUCreated} event atomically (catalog-product-spine, design D5/D8/D9; product-catalog —
 * Requirement: Composite SKU, Spine Creation Events; Module 0 PRD §3.8, §13.5 BR-SKU-2/5).
 *
 * Exactly ONE admissibility guard, and one deliberate NON-guard:
 *   - N ≥ 2 (BR-SKU-2): a Composite SKU is a bundle of at least two DISTINCT constituents. The incoming list is
 *     first normalised to its distinct constituents in input order (constituents are an ordered SET — the join's
 *     unique `(composite, PR)` makes a PR appear at most once per composite), then the count is checked. This is
 *     a cross-row count rule, so it is an in-action localized rejection (the same shape as the Master's dedup),
 *     NOT a DB constraint. It is pure input validation, so it runs BEFORE the transaction (like the Master's
 *     fail-closed type guard). Each constituent PR's EXISTENCE is enforced structurally by the join's
 *     `product_reference_id` foreign key — no redundant application-layer existence query (the 3.3 idiom: let the
 *     DB own structural constraints).
 *   - PRODUCER-AGNOSTIC (design D9 / BR-SKU-5): this action MUST NOT validate producer composition. A
 *     multi-producer constituent set is accepted as-is; single-producer admissibility is a Module S
 *     Offer-publication rule, never a PIM check. A "helpful" producer-uniformity guard here would be a boundary
 *     violation — its absence is the contract.
 *
 * Then, in ONE {@see DB::transaction}: insert the parent (`draft`), attach the ordered constituent links to the
 * `catalog_composite_sku_constituents` join (1-based `position` = input order), and record the PII-free event
 * via the platform {@see DomainEventRecorder} (the actor resolved from the {@see ActorContext} seam — System
 * until the auth ADR wires real principals in). The recorder's own transaction guard makes write + emit atomic.
 * The §3.8 atomicity-at-sale (BR-SKU-3) and immutability-after-active-Offer (BR-SKU-4) rules are runtime/commercial
 * concerns NOT enforced here — there is no lifecycle in this change (design D3). The model stays
 * persistence-only; this action is the seam those deferred changes extend.
 */
class CreateCompositeSku
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    /**
     * @param  list<int>  $productReferenceIds  the constituent PR ids, in bundle order
     */
    public function handle(array $productReferenceIds): CompositeSku
    {
        // Distinct constituents in input order. A Composite SKU is a SET of PRs (the join's unique
        // (composite, PR) makes a PR appear at most once), so duplicates collapse BEFORE the N ≥ 2 count and
        // the persisted positions stay contiguous 1..N.
        $constituents = array_values(array_unique($productReferenceIds));

        // N ≥ 2 (BR-SKU-2): pure input validation, before the tx. A bundle of fewer than two distinct
        // constituents is rejected with a localized reason (cross-row count rule — like the Master's dedup).
        if (count($constituents) < 2) {
            throw InsufficientCompositeConstituents::forCount(count($constituents));
        }

        return DB::transaction(function () use ($constituents): CompositeSku {
            $compositeSku = CompositeSku::create([
                'lifecycle_state' => LifecycleState::Draft,
            ]);

            // Ordered constituent links: 1-based position = input order. A single keyed attach inserts every
            // (composite, PR) link with its position; the join's FK enforces each PR's existence and its unique
            // keeps a PR at most once per composite. NO producer check (design D9 — producer-agnostic).
            $pivot = [];
            foreach ($constituents as $index => $productReferenceId) {
                $pivot[$productReferenceId] = ['position' => $index + 1];
            }
            $compositeSku->constituents()->attach($pivot);

            $this->recorder->record(
                name: CompositeSKUCreated::NAME,
                module: Module::Catalog->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: CompositeSKUCreated::ENTITY_TYPE,
                entityId: (string) $compositeSku->id,
                payload: CompositeSKUCreated::payload($compositeSku, $constituents),
            );

            return $compositeSku;
        });
    }
}
