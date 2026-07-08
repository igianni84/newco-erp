<?php

use App\Modules\Catalog\Actions\UpdateCompositeSkuComposition;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Exceptions\ActivationCascadeViolation;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalContentEdit;
use App\Modules\Catalog\Exceptions\InsufficientCompositeConstituents;
use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Audit\AuditRecord;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;

use function Pest\Laravel\actingAs;

/**
 * Pins `UpdateCompositeSkuComposition` — the Composite half of AC-0-BR-Audit-1, and the second real Action on
 * the shared `CatalogContentEdit` mechanism (catalog-module-0-completeness-sweep task 2.2; design D1/D2;
 * product-catalog — Requirement: Identity Edit and Re-Versioning; Module 0 PRD § 4.8 + § 13.3 BR-Audit-1,
 * § 3.8 + § 13.5 BR-SKU-2).
 *
 * A Composite SKU is attribute-free beyond lifecycle/audit, so its ordered constituent set IS its content: the
 * composition edit is this entity's identity edit, sharing the Master's `identity_updated` verb and its re-arm
 * semantics. The mechanism's five shared mechanics are pinned by `CatalogContentEditTest`; what is proven HERE
 * is everything the Action contributes: the distinct-then-count N ≥ 2 floor re-checked at edit time, the
 * activation cascade re-asserted on an `active` Composite (and NOT on a `draft`/`reviewed` one), the ordered
 * replace through the join table, and the before/after ordered id lists in the audit row.
 *
 * DatabaseMigrations (mirroring `UpdateProductMasterIdentityTest` / `CatalogContentEditTest`): the mechanism
 * opens its OWN top-level `DB::transaction`, so the audit recorder's `transactionLevel() === 0` guard sees a
 * real commit — which `RefreshDatabase`'s wrapping transaction would suppress. Fixtures come from the spine
 * FACTORIES, which bypass the creation Actions and so record neither audit rows nor domain events: every
 * `AuditRecord` / `DomainEvent` count below is attributable to the edit alone.
 */
uses(DatabaseMigrations::class);

/** A constituent Product Reference in $state — the factory bypasses `CreateProductReference`, recording no event. */
function compositionReference(LifecycleState $state = LifecycleState::Active): ProductReference
{
    return ProductReference::factory()->create(['lifecycle_state' => $state]);
}

/**
 * A Composite SKU in $state whose bundle is exactly $constituents, in argument order. The factory attaches two
 * default `draft` PRs to any composite born without constituents, so the fixture `sync()`s the wanted set over
 * them; `version` stays 1 (the factory's default) because nothing here goes through an Action.
 */
function compositionSku(LifecycleState $state, ProductReference ...$constituents): CompositeSku
{
    $sku = CompositeSku::factory()->create(['lifecycle_state' => $state]);

    $pivot = [];
    foreach (array_values($constituents) as $index => $constituent) {
        $pivot[$constituent->id] = ['position' => $index + 1];
    }
    $sku->constituents()->sync($pivot);

    return $sku->refresh();
}

/**
 * The persisted bundle, re-read in `position` order and cast to a clean `list<int>` (an uncast bigint reads back
 * as a numeric string under PostgreSQL's text protocol).
 *
 * @return list<int>
 */
function compositionOf(CompositeSku $sku): array
{
    return array_values(
        CompositeSku::findOrFail($sku->id)
            ->constituents()
            ->get()
            ->map(fn (ProductReference $constituent): int => (int) $constituent->id)
            ->all()
    );
}

/*
|--------------------------------------------------------------------------
| AC-0-BR-Audit-1 (Composite half) — the new version, the old bundle, the before/after
|--------------------------------------------------------------------------
*/

it('re-versions an active Composite composition edit in place, auditing the before and after ordered id lists and recording no domain event', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    [$first, $second, $third] = [compositionReference(), compositionReference(), compositionReference()];
    $sku = compositionSku(LifecycleState::Active, $first, $second);

    // Replace: drop $second, add $third, and put $third FIRST — an add, a remove and a reorder in one call.
    $edited = app(UpdateCompositeSkuComposition::class)->handle($sku, [$third->id, $first->id]);

    $persisted = CompositeSku::findOrFail($sku->id);

    // A NEW VERSION, in place: same row, same primary key, `version` incremented by EXACTLY one — and the
    // Composite stays `active` (the FSM has no `active → reviewed` edge; version + audit are the control).
    expect($edited->version)->toBe(2)
        ->and($persisted->id)->toBe($sku->id)
        ->and($persisted->version)->toBe(2)
        ->and($persisted->lifecycle_state)->toBe(LifecycleState::Active)
        // The bundle is the new ORDERED set: positions rewritten 1..N in the submitted order.
        ->and(compositionOf($sku))->toBe([$third->id, $first->id]);

    // The returned model's relation cache was dropped, so a caller reading it back sees the new bundle.
    expect($edited->constituents->map(fn (ProductReference $c): int => (int) $c->id)->all())
        ->toBe([$third->id, $first->id]);

    // ONE audit row under the content-edit envelope, sharing the Master's verb (a composition change IS the
    // Composite's identity change — design D5).
    $audit = AuditRecord::query()->sole();

    expect($audit->action)->toBe('catalog.composite_sku.identity_updated')
        ->and($audit->module)->toBe('catalog')
        ->and($audit->entity_type)->toBe('CompositeSku')
        ->and($audit->entity_id)->toBe((string) $sku->id)
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($audit->actor_id)->toEqual($operator->id)
        ->and($audit->authorization_basis)->toBe('catalog-content-edit')
        // toEqual, never toBe: PG's jsonb reorders a snapshot map's KEYS by key length (`version` before
        // `constituents` here) — while still comparing the nested LISTS element-wise by index, which is exactly
        // what an ORDERED constituent set needs.
        ->and($audit->before)->toEqual(['constituents' => [$first->id, $second->id], 'version' => 1])
        ->and($audit->after)->toEqual(['constituents' => [$third->id, $first->id], 'version' => 2]);

    // BR-Audit-1 spelled out: the OLD bundle is retrievable from the append-only trail even though the row has
    // moved on — "old versions are deprecated, never deleted".
    expect($audit->before)->toHaveKey('constituents', [$first->id, $second->id]);

    // The catalog event surface stays closed at the 21 lifecycle events (+ EnrichmentDataUpdated): a composition
    // edit records NO domain event (design D2).
    expect(DomainEvent::query()->count())->toBe(0);
});

it('treats a pure reorder of the same constituents as a real content change', function () {
    actingAs(Operator::factory()->create(), 'operator');

    [$first, $second] = [compositionReference(), compositionReference()];
    $sku = compositionSku(LifecycleState::Active, $first, $second);

    app(UpdateCompositeSkuComposition::class)->handle($sku, [$second->id, $first->id]);

    // Constituents are an ORDERED set: `position` is content, so swapping it versions the entity and is audited.
    expect(compositionOf($sku))->toBe([$second->id, $first->id])
        ->and(CompositeSku::findOrFail($sku->id)->version)->toBe(2);

    $audit = AuditRecord::query()->sole();

    expect($audit->before)->toEqual(['constituents' => [$first->id, $second->id], 'version' => 1])
        ->and($audit->after)->toEqual(['constituents' => [$second->id, $first->id], 'version' => 2]);
});

it('re-versions an identical ordered set without recording a content change, and collapses duplicate ids', function () {
    actingAs(Operator::factory()->create(), 'operator');

    [$first, $second] = [compositionReference(), compositionReference()];
    $sku = compositionSku(LifecycleState::Active, $first, $second);

    // Duplicates collapse to the distinct ids in input order (the join's unique keeps a PR at most once per
    // composite), leaving the bundle identical to the incumbent one.
    app(UpdateCompositeSkuComposition::class)->handle($sku, [$first->id, $second->id, $first->id]);

    // An edit is an edit: the mechanism's `version` increment and audit row are unconditional (only the
    // enrichment Action's own no-op rule short-circuits — design D11). The snapshots carry no content change.
    $audit = AuditRecord::query()->sole();

    expect(compositionOf($sku))->toBe([$first->id, $second->id])
        ->and(CompositeSku::findOrFail($sku->id)->version)->toBe(2)
        ->and($audit->before)->toEqual(['version' => 1])
        ->and($audit->after)->toEqual(['version' => 2]);
});

/*
|--------------------------------------------------------------------------
| The two re-checks: N ≥ 2 distinct (BR-SKU-2), and the cascade re-asserted at edit time
|--------------------------------------------------------------------------
*/

it('rejects a composition edit with fewer than two distinct constituents, leaving the bundle unchanged', function () {
    actingAs(Operator::factory()->create(), 'operator');

    [$first, $second] = [compositionReference(), compositionReference()];
    $sku = compositionSku(LifecycleState::Active, $first, $second);

    // Two ids, ONE distinct constituent: the floor is taken over the distinct set (BR-SKU-2), so this is a
    // one-element bundle, not a two-element one.
    expect(fn () => app(UpdateCompositeSkuComposition::class)->handle($sku, [$first->id, $first->id]))
        ->toThrow(InsufficientCompositeConstituents::class, 'at least two distinct');

    // The rejection fires inside the mechanism's transaction: nothing moved.
    expect(compositionOf($sku))->toBe([$first->id, $second->id])
        ->and(CompositeSku::findOrFail($sku->id)->version)->toBe(1)
        ->and(AuditRecord::query()->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('rejects a composition edit that would make an active Composite reference a non-active constituent', function () {
    actingAs(Operator::factory()->create(), 'operator');

    [$first, $second] = [compositionReference(), compositionReference()];
    $draft = compositionReference(LifecycleState::Draft);
    $sku = compositionSku(LifecycleState::Active, $first, $second);

    // The activation gate never runs again on an already-`active` Composite, so the edit path re-asserts the
    // cascade itself — otherwise this is the back door onto a non-`active` constituent.
    expect(fn () => app(UpdateCompositeSkuComposition::class)->handle($sku, [$first->id, $draft->id]))
        ->toThrow(ActivationCascadeViolation::class, 'must itself be active');

    expect(compositionOf($sku))->toBe([$first->id, $second->id])
        ->and(CompositeSku::findOrFail($sku->id)->version)->toBe(1)
        ->and(AuditRecord::query()->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('rejects a composition edit on an active Composite naming a Product Reference that does not resolve', function () {
    actingAs(Operator::factory()->create(), 'operator');

    [$first, $second] = [compositionReference(), compositionReference()];
    $sku = compositionSku(LifecycleState::Active, $first, $second);

    // Fail-closed, exactly as `ActivationCascadeGate` treats a null parent: an unresolved id is not `active`.
    // (In `draft`/`reviewed` the join's FK is what refuses an unknown id — the DB owns structural constraints.)
    expect(fn () => app(UpdateCompositeSkuComposition::class)->handle($sku, [$first->id, 999_999]))
        ->toThrow(ActivationCascadeViolation::class);

    expect(compositionOf($sku))->toBe([$first->id, $second->id])
        ->and(CompositeSku::findOrFail($sku->id)->version)->toBe(1)
        ->and(AuditRecord::query()->count())->toBe(0);
});

it('applies the same edit on a reviewed Composite without any constituent-state condition', function () {
    actingAs(Operator::factory()->create(), 'operator');

    [$first, $second] = [compositionReference(), compositionReference()];
    $draft = compositionReference(LifecycleState::Draft);
    $sku = compositionSku(LifecycleState::Reviewed, $first, $second);

    // A `reviewed` (or `draft`) bundle is assembled AHEAD of its parents: the cascade is the activation gate's
    // job, checked over the whole set when `ActivateCompositeSku` runs. The state check is `active`-scoped.
    app(UpdateCompositeSkuComposition::class)->handle($sku, [$first->id, $draft->id]);

    $persisted = CompositeSku::findOrFail($sku->id);

    expect(compositionOf($sku))->toBe([$first->id, $draft->id])
        ->and($persisted->version)->toBe(2)
        ->and($persisted->lifecycle_state)->toBe(LifecycleState::Reviewed)
        // The verb participates in review freshness: this `reviewed` Composite is now review-stale until it is
        // explicitly re-submitted (the derivation itself is pinned by `ReviewFreshnessVerbFilterTest`).
        ->and(AuditRecord::query()->sole()->action)->toBe('catalog.composite_sku.identity_updated');
});

/*
|--------------------------------------------------------------------------
| The mechanism's floors, through the real Action — both win over the Action's own re-checks
|--------------------------------------------------------------------------
*/

it('rejects a composition edit on a retired Composite, writing nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    [$first, $second] = [compositionReference(), compositionReference()];
    $sku = compositionSku(LifecycleState::Retired, $first, $second);

    // A ONE-element replacement on a `retired` Composite: the state guard runs BEFORE `$apply`, so the operator
    // reads the `retired` reason, never the N ≥ 2 count — the Action's re-checks never ran at all.
    expect(fn () => app(UpdateCompositeSkuComposition::class)->handle($sku, [$first->id]))
        ->toThrow(IllegalContentEdit::class, 'reopened');

    expect(compositionOf($sku))->toBe([$first->id, $second->id])
        ->and(CompositeSku::findOrFail($sku->id)->version)->toBe(1)
        ->and(AuditRecord::query()->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('rejects a composition edit under a system actor, writing nothing', function () {
    // No actingAs(): the ActorContext resolves (System, null) — a content edit is an inherently human decision,
    // and the floor is checked BEFORE the Action's re-checks ever run.
    [$first, $second, $third] = [compositionReference(), compositionReference(), compositionReference()];
    $sku = compositionSku(LifecycleState::Active, $first, $second);

    expect(fn () => app(UpdateCompositeSkuComposition::class)->handle($sku, [$first->id, $third->id]))
        ->toThrow(ApprovalGovernanceViolation::class);

    expect(compositionOf($sku))->toBe([$first->id, $second->id])
        ->and(CompositeSku::findOrFail($sku->id)->version)->toBe(1)
        ->and(AuditRecord::query()->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
});
