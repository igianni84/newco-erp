<?php

use App\Modules\Catalog\Actions\ActivateProductMaster;
use App\Modules\Catalog\Actions\CreateProductMaster;
use App\Modules\Catalog\Actions\SubmitProductMasterForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Enums\ProducerProjectionStatus;
use App\Modules\Catalog\Exceptions\ProducerActivationGateViolation;
use App\Modules\Catalog\Exceptions\UnknownCatalogReference;
use App\Modules\Catalog\Models\ProducerState;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\Support\Catalog\ProducerProjectionFixture;

use function Pest\Laravel\actingAs;

/**
 * The producer-existence guard on Master creation (catalog-module-0-completeness-sweep task 5.2; design D7;
 * product-catalog — Requirement: Product Master; Module 0 PRD AC-0-XM-2, RM-15).
 *
 * The whole of AC-0-XM-2 is one sentence with two halves, and this file pins the seam between them:
 * CREATION demands that the producer EXIST, ACTIVATION demands that it be ACTIVE. Both questions are answered
 * by the same Catalog-owned `catalog_producer_states` projection at different granularities (task 5.1 widened
 * it with `registered` precisely so the coarse question had an answer), and no second producer-knowledge
 * source was stood up. So a merely-`registered` producer yields a Master that SAVES and cannot be ACTIVATED —
 * the pairing that would collapse if either rule drifted toward the other.
 *
 * DatabaseMigrations (not RefreshDatabase): the activation leg drives the real approval chain, whose audit
 * recorder asserts `transactionLevel() === 0` — it must see genuine commits, exactly as
 * `ProductMasterLifecycleTest` documents.
 */
uses(DatabaseMigrations::class);

it('rejects a Product Master under a producer Catalog does not know, writing nothing (AC-0-XM-2)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // Producer 404 emitted nothing, so the projection has no row for it: an id that names nothing.
    expect(fn () => app(CreateProductMaster::class)->handle(
        name: 'Château Margaux',
        producerId: 404,
        appellation: 'Margaux',
        region: 'Bordeaux',
    ))->toThrow(UnknownCatalogReference::class, 'references Producer ids that do not exist (404)');

    // The guard runs inside the transaction BEFORE any write: neither the neutral core, nor the 1:1 wine
    // attribute set, nor the creation event survives. This is the whole protection — `producer_id` carries no
    // foreign key (invariant 10 forbids one), so nothing downstream would have caught it.
    expect(ProductMaster::query()->count())->toBe(0)
        ->and(DB::table('catalog_product_master_wine_attributes')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'ProductMasterCreated')->count())->toBe(0);
});

it('admits creation under a merely registered producer — existence is not activeness', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $master = app(CreateProductMaster::class)->handle(
        name: 'Château Margaux',
        producerId: ProducerProjectionFixture::known(7),
        appellation: 'Margaux',
        region: 'Bordeaux',
    );

    // Non-vacuity: the row really is only `registered` — the gate's status, unmet, yet creation passed.
    expect(ProducerState::query()->where('producer_id', 7)->sole()->status)
        ->toBe(ProducerProjectionStatus::Registered)
        ->and(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(DomainEvent::query()->where('name', 'ProductMasterCreated')->count())->toBe(1);
});

it('admits creation under a retired producer — retirement blocks new activation, never data entry', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $master = app(CreateProductMaster::class)->handle(
        name: 'Château Latour',
        producerId: ProducerProjectionFixture::known(8, ProducerProjectionStatus::Retired),
        appellation: 'Pauillac',
        region: 'Bordeaux',
    );

    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(DomainEvent::query()->where('name', 'ProductMasterCreated')->count())->toBe(1);
});

it('still blocks activation of a Master created under a registered producer (the creation/activation seam)', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // Creation is admitted by EXISTENCE...
    actingAs($creator, 'operator');
    $master = app(CreateProductMaster::class)->handle(
        name: 'Château Margaux',
        producerId: ProducerProjectionFixture::known(7),
        appellation: 'Margaux',
        region: 'Bordeaux',
    );

    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);

    // ...and activation is refused by ACTIVENESS. The gate (task 5.1, untouched here) still demands `active`.
    actingAs($approver, 'operator');
    expect(fn () => app(ActivateProductMaster::class)->handle($master))
        ->toThrow(ProducerActivationGateViolation::class);

    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(0);
});

it('names the unknown producer, not the identity collision, when both rejections could apply', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A Master already exists under producer 7 with this exact identity tuple...
    app(CreateProductMaster::class)->handle(
        name: 'Château Margaux',
        producerId: ProducerProjectionFixture::known(7),
        appellation: 'Margaux',
        region: 'Bordeaux',
    );

    // ...and then 7 leaves the projection (the state a pre-guard Master, or a purged read model, can reach).
    // Both guards now match the same input, so the ORDER inside the transaction is observable — and the
    // rejection an operator can act on is the root fact ("that producer does not exist"), not the downstream
    // consequence ("something else already claims this name"). Re-order the guards and this test reds.
    ProducerState::query()->where('producer_id', 7)->delete();

    // Asserting the exact class AND its message IS the precedence assertion. DuplicateProductMasterIdentity is
    // an unrelated sibling type carrying an unrelated reason, so neither half can be satisfied by the dedup
    // rejection — swap the two guards in CreateProductMaster and this expectation reds.
    expect(fn () => app(CreateProductMaster::class)->handle(
        name: 'Château Margaux',
        producerId: 7,
        appellation: 'Margaux',
        region: 'Bordeaux',
    ))->toThrow(UnknownCatalogReference::class, 'references Producer ids that do not exist (7)');

    // The second Master never landed — one row, the original.
    expect(ProductMaster::query()->count())->toBe(1);
});
