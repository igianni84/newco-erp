<?php

use App\Modules\Catalog\Actions\ActivateProductMaster;
use App\Modules\Catalog\Actions\ActivateProductVariant;
use App\Modules\Catalog\Actions\CreateProductMaster;
use App\Modules\Catalog\Actions\CreateProductVariant;
use App\Modules\Catalog\Actions\RejectProductMasterReview;
use App\Modules\Catalog\Actions\RejectProductVariantReview;
use App\Modules\Catalog\Actions\ResubmitProductMasterForReview;
use App\Modules\Catalog\Actions\ResubmitProductVariantForReview;
use App\Modules\Catalog\Actions\SubmitProductMasterForReview;
use App\Modules\Catalog\Actions\SubmitProductVariantForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Module;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages\ViewProductMaster;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductVariantResource\Pages\ViewProductVariant;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Task 1.2 (catalog-module-0-completeness-sweep; design D4/D9, risks R3/R4; product-catalog — Requirement:
 * Approval Governance) — the review-freshness condition is derived from a VERB-FILTERED read of the catalog
 * audit trail, not from its raw latest action, and the console's re-submit visibility mirrors it exactly.
 *
 * The shipped RM-06 gate read the entity's latest `audit_records.action` and blocked iff it ended `.rejected`.
 * That was safe only while `LifecycleTransition` was the SOLE writer of `catalog.*` audit rows. This change ends
 * that era (identity edits, enrichment updates and whitelist maintenance all write catalog audit rows), which
 * opens the **S1 hole**: a post-rejection enrichment row would become the raw latest action and silently unblock
 * activation. The fix — among the entity's audit actions ending in one of the four REVIEW-FRESHNESS-RELEVANT
 * suffixes (`.submitted`, `.resubmitted`, `.rejected`, `.identity_updated`) the LATEST wins; it is review-STALE
 * iff that action ends `.rejected` or `.identity_updated` — is enforced in `ApprovalGovernance` and MIRRORED in
 * `OperatorConsoleViewRecord::isReviewStale()` (the console may not import `Catalog\Lifecycle`, design D9).
 *
 * The edit Actions do not exist yet (they land in tasks 1.3/2.x/4.x), so an edit row is simulated by writing it
 * through the platform {@see AuditRecorder} — the very writer `CatalogContentEdit` will use — inside a real
 * transaction (risk R4: tests may do what domain code does; the real-Action paths re-prove the same behaviour
 * end-to-end in tasks 2.3 and 4.1). The verbs written here are the REAL ones the design assigns to each entity:
 * `identity_updated` on the Master, `enrichment_updated` / `whitelist_updated` on the Variant — no fictitious
 * verb is invented to make a point.
 *
 * DatabaseMigrations (consistent with every sibling `*LifecycleTest`): each Action opens its OWN top-level
 * DB::transaction, so the recorder commits at `transactionLevel() === 0` and the inline `ProducerLifecycleProjector`
 * fires on the post-commit hook — which `RefreshDatabase`'s wrapping transaction would suppress.
 */
uses(DatabaseMigrations::class);

/**
 * Project a Producer `active` in Catalog's own read model (the Product Master activation gate's source) by
 * recording a Module-K `ProducerActivated` inside a real transaction, so the inline projector upserts
 * `catalog_producer_states`. Distinctly named for the one shared Pest function namespace.
 */
function verbFilterProjectProducerActive(int $producerId): void
{
    DB::transaction(fn () => app(DomainEventRecorder::class)->record(
        name: 'ProducerActivated',
        module: Module::Parties->value,
        actorRole: ActorRole::System,
        actorId: null,
        entityType: 'Producer',
        entityId: (string) $producerId,
        payload: ['producer_id' => $producerId, 'status' => 'active'],
    ));
}

/**
 * Write ONE `catalog.<segment>.<verb>` audit row for $model through the platform recorder, exactly as the
 * forthcoming `CatalogContentEdit` mechanic will (module `catalog`, the operator envelope, a before/after pair).
 * This is how a not-yet-existing edit path's audit footprint is simulated (risk R4) — the derivation under test
 * reads `audit_records.action`, so the row's provenance is irrelevant to it, only its verb and its id order.
 */
function verbFilterAuditRow(string $entityType, string $segment, string $verb, int $entityId, int $actorId): void
{
    DB::transaction(fn () => app(AuditRecorder::class)->record(
        action: "catalog.{$segment}.{$verb}",
        module: Module::Catalog->value,
        actorRole: ActorRole::NewcoOps,
        actorId: $actorId,
        entityType: $entityType,
        entityId: (string) $entityId,
        before: ['version' => 1],
        after: ['version' => 2],
        authorizationBasis: 'catalog-lifecycle',
    ));
}

/** The Master's identity-edit audit row (the review-freshness-relevant `identity_updated` verb). */
function verbFilterMasterIdentityEdit(ProductMaster $master, Operator $editor): void
{
    verbFilterAuditRow('ProductMaster', 'product_master', 'identity_updated', $master->id, $editor->id);
}

/** Build a Product Master to `reviewed` under an `active`-projected Producer, created by $creator, submitted by $reviewer. */
function verbFilterReviewedMaster(Operator $creator, Operator $reviewer): ProductMaster
{
    verbFilterProjectProducerActive(7);

    actingAs($creator, 'operator');
    $master = app(CreateProductMaster::class)->handle(name: 'Château Margaux', producerId: 7, appellation: 'Margaux', region: 'Bordeaux');

    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);

    return $master->refresh();
}

/*
|--------------------------------------------------------------------------
| The S1 hole — a non-governance audit row must neither clear nor set the block
|--------------------------------------------------------------------------
|
| Design D4's first consequence, and the reason this task must land BEFORE task 1.3 introduces the second catalog
| audit writer. Under the shipped raw latest-action read, the enrichment row below would be the freshest action,
| it does not end `.rejected`, and the un-remediated rejection underneath it would silently stop blocking — a
| rejected Variant activating with no re-submit. The verb filter makes those rows INVISIBLE to the derivation.
*/

it('does not clear a pending rejection when later enrichment and whitelist audit rows land on top of it', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // A factory-`active` parent Master opens the within-module cascade gate, so the review-freshness BLOCK is
    // the only thing that can refuse this activation.
    $master = ProductMaster::factory()->create(['lifecycle_state' => LifecycleState::Active]);

    actingAs($creator, 'operator');
    $variant = app(CreateProductVariant::class)->handle(productMasterId: $master->id, variantIdentifier: '2015');
    actingAs($reviewer, 'operator');
    app(SubmitProductVariantForReview::class)->handle($variant);
    app(RejectProductVariantReview::class)->handle($variant, 'Vintage year is missing.');

    // Two REAL Variant-scoped maintenance rows land after the rejection — the exact S1 shape. Both are now the
    // raw latest action; neither ends in a review-freshness-relevant suffix.
    verbFilterAuditRow('ProductVariant', 'product_variant', 'enrichment_updated', $variant->id, $creator->id);
    verbFilterAuditRow('ProductVariant', 'product_variant', 'whitelist_updated', $variant->id, $creator->id);

    // The console still OFFERS re-submit — the operator's remedy stays reachable (design D9/R3: the console read
    // and the domain block must not disagree the moment a maintenance row lands).
    actingAs($approver, 'operator');
    Livewire::test(ViewProductVariant::class, ['record' => $variant->getKey()])
        ->assertActionVisible('resubmit');

    // And the domain still BLOCKS: the latest RELEVANT action is the rejection. `un-remediated` discriminates the
    // rejection cause from the edit cause and from every separation-of-duties reason.
    expect(fn () => app(ActivateProductVariant::class)->handle($variant))
        ->toThrow(ApprovalGovernanceViolation::class, 'un-remediated');

    expect(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'ProductVariantActivated')->count())->toBe(0);

    // Only the explicit re-submit re-arms review; the maintenance rows never could.
    actingAs($creator, 'operator');
    app(ResubmitProductVariantForReview::class)->handle($variant);

    actingAs($approver, 'operator');
    $active = app(ActivateProductVariant::class)->handle($variant);

    expect($active->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(DomainEvent::query()->where('name', 'ProductVariantActivated')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| The edit leg — an identity edit in `reviewed` re-arms review (the deferred DEC-019 leg)
|--------------------------------------------------------------------------
|
| Design D4's second consequence: `.identity_updated` joins `.rejected` as a STALE verb. An approver may not
| approve content that changed after the last review decision. The remedy is the same explicit re-submit, but the
| reason names the other fact — `edited` (not `un-remediated`).
*/

it('blocks activation while an identity edit is the latest review-freshness action, and admits it after a re-submit', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $master = verbFilterReviewedMaster($creator, $reviewer);

    // The Creator edits review-governed identity content while the Master sits in `reviewed`, never rejected.
    verbFilterMasterIdentityEdit($master, $creator);

    // The console offers re-submit on this cause too (not only on a rejection) — design D9's "BOTH stale causes".
    actingAs($approver, 'operator');
    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->assertActionVisible('resubmit');

    expect(fn () => app(ActivateProductMaster::class)->handle($master))
        ->toThrow(ApprovalGovernanceViolation::class, 'edited');

    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(0);

    actingAs($creator, 'operator');
    app(ResubmitProductMasterForReview::class)->handle($master);

    actingAs($approver, 'operator');
    $active = app(ActivateProductMaster::class)->handle($master);

    expect($active->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(1);
});

it('lets a draft-stage identity edit through — the following submit is itself a review-freshness action that clears it', function () {
    // Design D4: `.submitted` MUST be in the relevant set. Were it excluded, the draft edit below would remain the
    // latest relevant action forever and the Master could never be activated at all.
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    verbFilterProjectProducerActive(7);

    actingAs($creator, 'operator');
    $master = app(CreateProductMaster::class)->handle(name: 'Château Margaux', producerId: 7, appellation: 'Margaux', region: 'Bordeaux');

    // Edited freely in `draft` — no review has happened yet, so there is nothing to re-arm.
    verbFilterMasterIdentityEdit($master, $creator);

    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);

    // The submit is now the latest relevant action: not stale. The console hides the redundant re-submit…
    actingAs($approver, 'operator');
    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->assertActionHidden('resubmit');

    // …and the distinct approver activates.
    $active = app(ActivateProductMaster::class)->handle($master);

    expect($active->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Uniformity — the console read and the domain block agree on every history (design D9, risk R3)
|--------------------------------------------------------------------------
|
| `OperatorConsoleViewRecord::isReviewStale()` cannot import `ApprovalGovernance` (the console's import boundary
| admits only the operated module's Models/Actions + operand enums), so the predicate is DUPLICATED. Duplication
| drifts unless something pins it: for each audit history below, the console's re-submit visibility and the domain's
| activation verdict are asserted TOGETHER — visible ⇔ blocked, hidden ⇔ activates. Any divergence reds this test.
|
| Verbs used are the Master's real ones (`submitted`/`resubmitted`/`rejected`/`identity_updated` from the four
| relevant suffixes, plus nothing else — the Master has no enrichment/whitelist surface; that noise case is proven
| on the Variant above, where those verbs genuinely live).
*/

it('keeps the console re-submit visibility in lock-step with the domain block-gate on every audit history', function (string $history, bool $stale, ?string $token) {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $master = verbFilterReviewedMaster($creator, $reviewer);

    // Each arm leaves a DIFFERENT latest review-freshness-relevant action on the same fixture. The reviewer is the
    // acting principal on entry (they submitted), which is who may reject; the creator re-submits.
    $applyHistory = match ($history) {
        'fresh submit' => function (): void {},
        'rejected' => function () use ($master): void {
            app(RejectProductMasterReview::class)->handle($master, 'Provenance note needs work.');
        },
        'rejected then re-submitted' => function () use ($master, $creator): void {
            app(RejectProductMasterReview::class)->handle($master, 'Provenance note needs work.');
            actingAs($creator, 'operator');
            app(ResubmitProductMasterForReview::class)->handle($master);
        },
        'identity edited' => function () use ($master, $creator): void {
            verbFilterMasterIdentityEdit($master, $creator);
        },
        'identity edited then re-submitted' => function () use ($master, $creator): void {
            verbFilterMasterIdentityEdit($master, $creator);
            actingAs($creator, 'operator');
            app(ResubmitProductMasterForReview::class)->handle($master);
        },
        default => throw new InvalidArgumentException("Unhandled history: {$history}"),
    };

    $applyHistory();

    // CONSOLE — read-only, so it must be observed before the activation attempt mutates anything.
    actingAs($approver, 'operator');
    $page = Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()]);

    if ($stale) {
        $page->assertActionVisible('resubmit');
    } else {
        $page->assertActionHidden('resubmit');
    }

    // DOMAIN — the same history, the same verdict. The distinct approver and the open Producer gate are fixed
    // across every case, so the review-freshness condition is the only variable.
    if ($stale) {
        expect(fn () => app(ActivateProductMaster::class)->handle($master))
            ->toThrow(ApprovalGovernanceViolation::class, (string) $token);

        expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
            ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(0);

        return;
    }

    expect(app(ActivateProductMaster::class)->handle($master)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(1);
})->with([
    // [history, review-stale?, the discriminating token of the expected block reason]
    ['fresh submit', false, null],
    ['rejected', true, 'un-remediated'],
    ['rejected then re-submitted', false, null],
    ['identity edited', true, 'edited'],
    ['identity edited then re-submitted', false, null],
]);
