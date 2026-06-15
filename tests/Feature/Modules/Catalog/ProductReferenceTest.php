<?php

use App\Modules\Catalog\Actions\CreateProductReference;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\ProductReferenceCreated;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the Product Reference — the atomic product identity and the universal product key, a SINGLE-table spine
 * entity composed of EXACTLY two dimensions, a Variant and a Format (catalog-product-spine task 3.3; design
 * D5/D8; product-catalog — Requirement: Product Reference — the atomic product key, Spine Creation Events). It
 * proves the CreateProductReference action persists the row in `draft` over one Variant + one Format, records
 * ProductReferenceCreated through the platform recorder in the SAME transaction (PII-free), keeps a Case
 * Configuration OUT of PR identity (BR-Identity-3 — no `case_configuration_id`), enforces the two-dimension
 * identity as a DB UNIQUE on the `(variant, format)` pair (a duplicate is rejected; the same pair resolves to
 * the one PR — the "Packaging does not change the PR" uniqueness half), and holds the scope guard (no
 * transition out of `draft`).
 *
 * RefreshDatabase (per the task hint): the action opens its OWN DB::transaction, so the recorder's
 * `transactionLevel() === 0` guard is satisfied by the savepoint even under the wrapper — AND a duplicate
 * insert's UniqueConstraintViolationException is raised inside that savepoint (trap 5), so on PostgreSQL the
 * failure is rolled back to the savepoint and the outer test transaction survives for the follow-on
 * assertions. Event payload is asserted BY KEY — never a byte-compare of stored JSON (knowledge/testing trap 3).
 */
uses(RefreshDatabase::class);

it('creates a Product Reference in draft from a Variant + Format', function () {
    $variant = ProductVariant::factory()->create();
    $format = Format::factory()->create();

    $reference = app(CreateProductReference::class)->handle(
        productVariantId: $variant->id,
        formatId: $format->id,
    );

    // Re-fetch so the assertions exercise the read/hydration casts, not the in-memory create() values.
    $read = ProductReference::findOrFail($reference->id);

    expect($read->product_variant_id)->toBe($variant->id)
        ->and($read->format_id)->toBe($format->id)
        ->and($read->lifecycle_state)->toBe(LifecycleState::Draft)  // born draft (design D3)
        ->and($read->version)->toBe(1);                            // §4.8 version floor, born at 1
});

it('records a ProductReferenceCreated domain event in the same transaction, tagged catalog and PII-free', function () {
    $variant = ProductVariant::factory()->create();
    $format = Format::factory()->create();

    $reference = app(CreateProductReference::class)->handle(
        productVariantId: $variant->id,
        formatId: $format->id,
    );

    // sole() asserts EXACTLY one ProductReferenceCreated row exists — the one-event contract.
    $event = DomainEvent::query()->where('name', ProductReferenceCreated::NAME)->sole();

    expect($event->module)->toBe('catalog')                    // Module::Catalog->value
        ->and($event->entity_type)->toBe('ProductReference')
        ->and($event->entity_id)->toBe((string) $reference->id) // envelope entity_id is a string
        ->and($event->actor_role)->toBe(ActorRole::System);    // the ActorContext seam default

    // Payload asserted BY KEY through the array cast (trap 3); PII-free — the two identity dimensions by id.
    expect($event->payload['product_reference_id'])->toBe($reference->id)
        ->and($event->payload['product_variant_id'])->toBe($variant->id)
        ->and($event->payload['format_id'])->toBe($format->id)
        ->and($event->payload['lifecycle_state'])->toBe('draft');

    // BR-Identity-3: a Case Configuration is never part of PR identity — absent from the contract payload.
    expect($event->payload)->not->toHaveKey('case_configuration_id');
});

it('references exactly one Variant and one Format via the within-module belongsTo relations', function () {
    $variant = ProductVariant::factory()->create();
    $format = Format::factory()->create();

    $reference = app(CreateProductReference::class)->handle(
        productVariantId: $variant->id,
        formatId: $format->id,
    );
    $read = ProductReference::findOrFail($reference->id);

    // both within-module belongsTo resolve to exactly one parent each (sole() = exactly one, non-null).
    expect($read->variant()->sole()->id)->toBe($variant->id)
        ->and($read->variant()->sole())->toBeInstanceOf(ProductVariant::class)
        ->and($read->format()->sole()->id)->toBe($format->id)
        ->and($read->format()->sole())->toBeInstanceOf(Format::class);
});

it('carries no case-configuration dimension — packaging is never part of PR identity (BR-Identity-3)', function () {
    $variant = ProductVariant::factory()->create();
    $format = Format::factory()->create();

    app(CreateProductReference::class)->handle(productVariantId: $variant->id, formatId: $format->id);

    // BR-Identity-3 / AC-0-BR-Identity-3: the PR has no case-configuration column.
    expect(Schema::hasColumn('catalog_product_references', 'case_configuration_id'))->toBeFalse();

    // No column carries the case-configuration concept as a substring (catches a renamed-but-present column) —
    // the strongest leg of the absence guard. One matcher per expect() (the facade yields mixed elements).
    $columns = Schema::getColumnListing('catalog_product_references');

    foreach ($columns as $column) {
        expect($column)->not->toContain('case_config');
    }

    // The exact neutral column set (sorted: order-independent, cross-engine stable) — exactly the two identity
    // dimensions (product_variant_id, format_id) + lifecycle/audit, and nothing else.
    sort($columns);

    expect($columns)->toBe([
        'created_at', 'format_id', 'id', 'lifecycle_state', 'product_variant_id', 'updated_at', 'version',
    ]);
});

it('rejects a duplicate (variant, format) pair at the DB unique index — the same pair resolves to one PR', function () {
    $variant = ProductVariant::factory()->create();
    $format = Format::factory()->create();

    $first = app(CreateProductReference::class)->handle(productVariantId: $variant->id, formatId: $format->id);

    // A second identical pair is rejected by the DB unique index (BR-Identity-3). The action's own
    // DB::transaction is the savepoint (trap 5): on PostgreSQL the violation rolls back to it and the outer
    // test transaction survives for the assertions below.
    expect(fn () => app(CreateProductReference::class)->handle(productVariantId: $variant->id, formatId: $format->id))
        ->toThrow(UniqueConstraintViolationException::class);

    // The rejected duplicate recorded NO event (the insert aborts before the recorder runs).
    expect(DomainEvent::query()->where('name', ProductReferenceCreated::NAME)->count())->toBe(1);

    // The (variant, format) pair resolves to exactly ONE PR — the first — the universal-key identity rule:
    // every later Sellable SKU for this Variant + Format (loose / OWC / carton) points at this one PR.
    $matches = ProductReference::query()
        ->where('product_variant_id', $variant->id)
        ->where('format_id', $format->id)
        ->get();

    expect($matches)->toHaveCount(1)
        ->and($matches->sole()->id)->toBe($first->id);
});

it('enforces uniqueness on the (variant, format) pair, not either dimension alone', function () {
    $variantA = ProductVariant::factory()->create();
    $variantB = ProductVariant::factory()->create();
    $formatA = Format::factory()->create();
    $formatB = Format::factory()->create();

    $pair = app(CreateProductReference::class)->handle(productVariantId: $variantA->id, formatId: $formatA->id);
    // same Variant, different Format → a DISTINCT PR (the Format dimension differentiates identity).
    $sameVariant = app(CreateProductReference::class)->handle(productVariantId: $variantA->id, formatId: $formatB->id);
    // different Variant, same Format → a DISTINCT PR (the Variant dimension differentiates identity).
    $sameFormat = app(CreateProductReference::class)->handle(productVariantId: $variantB->id, formatId: $formatA->id);

    expect($pair->id)->not->toBe($sameVariant->id)
        ->and($pair->id)->not->toBe($sameFormat->id)
        ->and($sameVariant->id)->not->toBe($sameFormat->id)
        ->and(ProductReference::query()->count())->toBe(3);
});

it('records no lifecycle-transition event — the Reference stays draft (scope guard)', function () {
    $variant = ProductVariant::factory()->create();
    $format = Format::factory()->create();

    $reference = app(CreateProductReference::class)->handle(productVariantId: $variant->id, formatId: $format->id);

    // Design D3 scope guard: only the *Created event exists — never an *Activated/*Retired (the deferred
    // catalog-lifecycle-approval change owns those).
    expect(DomainEvent::query()->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Retired%')->count())->toBe(0)
        ->and(ProductReference::findOrFail($reference->id)->lifecycle_state)->toBe(LifecycleState::Draft);
});

it('produces a draft Reference via the factory without recording an event', function () {
    // The factory is a pure fixture: it bypasses the action, so it persists a draft Reference (and a parent
    // Variant + Format) but records no ProductReferenceCreated (task 4.1 leans on it for a parent PR).
    $reference = ProductReference::factory()->create();

    expect($reference->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and($reference->version)->toBe(1)
        ->and($reference->variant()->sole())->toBeInstanceOf(ProductVariant::class)  // within-module parents attached
        ->and($reference->format()->sole())->toBeInstanceOf(Format::class)
        ->and(DomainEvent::query()->count())->toBe(0);                               // the factory records no event
});
