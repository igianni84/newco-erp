<?php

use App\Modules\Catalog\Actions\CreateCompositeSku;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\CompositeSKUCreated;
use App\Modules\Catalog\Exceptions\InsufficientCompositeConstituents;
use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the Composite SKU — a curated bundle of N ≥ 2 ordered constituent Product References, the spine's only
 * many-to-many entity (catalog-product-spine task 4.2; design D5/D8/D9; product-catalog — Requirement: Composite
 * SKU, Spine Creation Events; Module 0 PRD §3.8, §13.5 BR-SKU-2/5). It proves the CreateCompositeSku action
 * persists the parent in `draft` with its ordered constituents, records CompositeSKUCreated through the platform
 * recorder in the SAME transaction (PII-free), enforces N ≥ 2 over the DISTINCT constituents (a sub-two bundle is
 * rejected before any write), is PRODUCER-AGNOSTIC (a multi-producer set is accepted — no producer check, design
 * D9 / BR-SKU-5), realises the constituent relationship as a true M:N (one PR across two composites), and holds
 * the scope guard (no transition out of `draft`).
 *
 * RefreshDatabase (per the task hint): the action opens its OWN DB::transaction, so the recorder's
 * `transactionLevel() === 0` guard is satisfied by the savepoint even under the wrapper. The N ≥ 2 guard runs
 * BEFORE the transaction (pure input validation), so a rejected creation leaves no parent row and no event. Event
 * payload is asserted BY KEY — never a byte-compare of stored JSON (knowledge/testing trap 3).
 */
uses(RefreshDatabase::class);

it('creates a Composite SKU in draft with its ordered constituents (N ≥ 2)', function () {
    $prA = ProductReference::factory()->create();
    $prB = ProductReference::factory()->create();

    $composite = app(CreateCompositeSku::class)->handle([$prA->id, $prB->id]);

    // Re-fetch so the assertions exercise the read/hydration casts, not the in-memory create() values.
    $read = CompositeSku::findOrFail($composite->id);

    expect($read->lifecycle_state)->toBe(LifecycleState::Draft)  // born draft (design D3)
        ->and($read->version)->toBe(1)                          // §4.8 version floor, born at 1
        ->and($read->constituents)->toHaveCount(2)              // the N ≥ 2 bundle
        ->and($read->constituents->pluck('id')->all())->toBe([$prA->id, $prB->id]); // ordered constituents
});

it('records a CompositeSKUCreated domain event in the same transaction, tagged catalog and PII-free', function () {
    $prA = ProductReference::factory()->create();
    $prB = ProductReference::factory()->create();

    $composite = app(CreateCompositeSku::class)->handle([$prA->id, $prB->id]);

    // sole() asserts EXACTLY one CompositeSKUCreated row exists — the one-event contract.
    $event = DomainEvent::query()->where('name', CompositeSKUCreated::NAME)->sole();

    expect($event->module)->toBe('catalog')                    // Module::Catalog->value
        ->and($event->entity_type)->toBe('CompositeSku')       // the canonical model class name (§18)
        ->and($event->entity_id)->toBe((string) $composite->id) // envelope entity_id is a string
        ->and($event->actor_role)->toBe(ActorRole::System);    // the ActorContext seam default

    // Payload asserted BY KEY through the array cast (trap 3); PII-free — ids only (a Composite references no
    // party; it is producer-agnostic). The ordered constituent id list is a JSON array (order-stable on jsonb).
    expect($event->payload['composite_sku_id'])->toBe($composite->id)
        ->and($event->payload['constituent_product_reference_ids'])->toBe([$prA->id, $prB->id])
        ->and($event->payload['constituent_count'])->toBe(2)
        ->and($event->payload['lifecycle_state'])->toBe('draft');
});

it('rejects a single-constituent Composite — and persists nothing', function () {
    $pr = ProductReference::factory()->create();

    // N ≥ 2 (BR-SKU-2): a one-constituent bundle is rejected. The guard runs before the transaction.
    expect(fn () => app(CreateCompositeSku::class)->handle([$pr->id]))
        ->toThrow(InsufficientCompositeConstituents::class);

    // No parent row, no event — the rejection precedes any write (clean, no orphan bundle).
    expect(CompositeSku::query()->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', CompositeSKUCreated::NAME)->count())->toBe(0);
});

it('rejects an empty-constituent Composite', function () {
    expect(fn () => app(CreateCompositeSku::class)->handle([]))
        ->toThrow(InsufficientCompositeConstituents::class);

    expect(CompositeSku::query()->count())->toBe(0);
});

it('rejects a bundle whose constituents collapse to fewer than two distinct PRs', function () {
    $pr = ProductReference::factory()->create();

    // Constituents are an ordered SET (the join's unique (composite, PR) makes a PR appear at most once), so
    // [A, A] is ONE distinct constituent — below the N ≥ 2 floor, rejected.
    expect(fn () => app(CreateCompositeSku::class)->handle([$pr->id, $pr->id]))
        ->toThrow(InsufficientCompositeConstituents::class);

    expect(CompositeSku::query()->count())->toBe(0);
});

it('de-duplicates repeated constituents to the distinct set', function () {
    $prA = ProductReference::factory()->create();
    $prB = ProductReference::factory()->create();

    // [A, A, B] → the two distinct constituents A, B (the repeat collapses; positions stay contiguous 1..N).
    $composite = app(CreateCompositeSku::class)->handle([$prA->id, $prA->id, $prB->id]);

    expect($composite->constituents)->toHaveCount(2)
        ->and($composite->constituents->pluck('id')->all())->toBe([$prA->id, $prB->id]);
});

it('is producer-agnostic — accepts constituents drawn from more than one producer (BR-SKU-5)', function () {
    // Two full chains whose Masters carry DIFFERENT producers (producer_id is a plain column — no relation,
    // invariant 10). The constituent set is genuinely multi-producer.
    $masterA = ProductMaster::factory()->create(['producer_id' => 1001]);
    $prA = ProductReference::factory()->create([
        'product_variant_id' => ProductVariant::factory()->create(['product_master_id' => $masterA->id])->id,
    ]);

    $masterB = ProductMaster::factory()->create(['producer_id' => 2002]);
    $prB = ProductReference::factory()->create([
        'product_variant_id' => ProductVariant::factory()->create(['product_master_id' => $masterB->id])->id,
    ]);

    expect($masterA->producer_id)->not->toBe($masterB->producer_id); // the set really is multi-producer

    // PIM accepts the multi-producer bundle WITHOUT validating producer composition (design D9): the
    // single-producer-at-launch rule is a Module S Offer-publication concern, never a PIM check. The creation
    // simply succeeding with both constituents is the proof that no producer guard ran.
    $composite = app(CreateCompositeSku::class)->handle([$prA->id, $prB->id]);

    expect($composite->constituents)->toHaveCount(2)
        ->and(DomainEvent::query()->where('name', CompositeSKUCreated::NAME)->count())->toBe(1);
});

it('lets the same Product Reference be a constituent of two different Composites (M:N)', function () {
    $shared = ProductReference::factory()->create();
    $otherOne = ProductReference::factory()->create();
    $otherTwo = ProductReference::factory()->create();

    $first = app(CreateCompositeSku::class)->handle([$shared->id, $otherOne->id]);
    $second = app(CreateCompositeSku::class)->handle([$shared->id, $otherTwo->id]);

    // Both composites are valid and both list the shared PR — the constituent relationship is many-to-many.
    expect($first->constituents->pluck('id')->all())->toContain($shared->id)
        ->and($second->constituents->pluck('id')->all())->toContain($shared->id)
        ->and($first->id)->not->toBe($second->id);

    // The shared PR has TWO constituent links — one per composite (the join's unique is per (composite, PR),
    // so the same PR across DIFFERENT composites is allowed).
    expect(DB::table('catalog_composite_sku_constituents')->where('product_reference_id', $shared->id)->count())->toBe(2);
});

it('preserves constituent order by position — not by id', function () {
    // Created in id order pr1 < pr2 < pr3, then supplied in a DIFFERENT order. If the constituents come back in
    // the SUPPLIED order (not sorted by id), the `position` ordering is doing the work.
    $pr1 = ProductReference::factory()->create();
    $pr2 = ProductReference::factory()->create();
    $pr3 = ProductReference::factory()->create();

    $composite = app(CreateCompositeSku::class)->handle([$pr3->id, $pr1->id, $pr2->id]);
    $read = CompositeSku::findOrFail($composite->id);

    expect($read->constituents->pluck('id')->all())->toBe([$pr3->id, $pr1->id, $pr2->id]) // supplied order
        ->and($read->constituents)->toHaveCount(3)
        ->and($read->constituents->pluck('id')->all())->not->toBe([$pr1->id, $pr2->id, $pr3->id]); // not id order
});

it('pins the structural column sets — the parent carries no commercial attributes; the join is a pure link', function () {
    // §3.8: the Composite SKU is "cheap at PIM (registration + lifecycle only)" — the parent has NO commercial
    // name / marketing copy / flags, only lifecycle + audit. Sorted: order-independent, cross-engine stable.
    $parent = Schema::getColumnListing('catalog_composite_skus');
    sort($parent);

    expect($parent)->toBe(['created_at', 'id', 'lifecycle_state', 'updated_at', 'version']);

    // The join is a pure link table: the two FKs + position + nothing else (no surrogate id, no timestamps — the
    // bundle's audit lives on the parent + its event; the natural key is the unique (composite, PR) pair).
    $join = Schema::getColumnListing('catalog_composite_sku_constituents');
    sort($join);

    expect($join)->toBe(['composite_sku_id', 'position', 'product_reference_id']);
});

it('records no lifecycle-transition event — the Composite stays draft (scope guard)', function () {
    $composite = app(CreateCompositeSku::class)->handle([
        ProductReference::factory()->create()->id,
        ProductReference::factory()->create()->id,
    ]);

    // Design D3 scope guard: only the *Created event exists — never an *Activated/*Retired (the §3.8
    // immutability-after-active-Offer + atomicity-at-sale rules belong to the deferred catalog-lifecycle-approval
    // change / Module S).
    expect(DomainEvent::query()->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Retired%')->count())->toBe(0)
        ->and(CompositeSku::findOrFail($composite->id)->lifecycle_state)->toBe(LifecycleState::Draft);
});

it('produces a draft Composite with two constituents via the factory without recording an event', function () {
    // The factory is a pure fixture: it bypasses the action, so it persists a draft two-constituent bundle (and
    // the parent PRs) but records no CompositeSKUCreated and runs no N ≥ 2 guard.
    $composite = CompositeSku::factory()->create();

    expect($composite->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and($composite->version)->toBe(1)
        ->and($composite->constituents)->toHaveCount(2)               // a valid bundle out of the box
        ->and(DomainEvent::query()->count())->toBe(0);               // the factory records no event
});
