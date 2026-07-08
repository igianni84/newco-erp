<?php

use App\Modules\Catalog\Actions\ActivateProductVariant;
use App\Modules\Catalog\Actions\CreateProductVariant;
use App\Modules\Catalog\Actions\RejectProductVariantReview;
use App\Modules\Catalog\Actions\ResubmitProductVariantForReview;
use App\Modules\Catalog\Actions\RetireProductVariant;
use App\Modules\Catalog\Actions\SubmitProductVariantForReview;
use App\Modules\Catalog\Actions\UpdateProductVariantEnrichment;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Events\EnrichmentDataUpdated;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalContentEdit;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\ProductVariantWineAttributes;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Audit\AuditRecord;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use App\Platform\I18n\TranslatableText;
use Illuminate\Foundation\Testing\DatabaseMigrations;

use function Pest\Laravel\actingAs;

/**
 * Pins `UpdateProductVariantEnrichment` and the `EnrichmentDataUpdated` event it records — the observational
 * enrichment path (catalog-module-0-completeness-sweep task 4.1; design D2/D11; product-catalog — Requirement:
 * Enrichment Data Update; Module 0 PRD § 14.1 last paragraph, § 9.1, § 13.3 BR-Audit-2; AC-0-EVT-8).
 *
 * Enrichment is the mirror image of an identity edit, and the four NEGATIVE facts carry as much of the spec as
 * the positive one: no `version` change, no re-arm of review, no lifecycle movement — and, uniquely in Module 0,
 * NO WRITE AT ALL when the incoming value equals the stored one. That last is the whole reason the event is
 * meaningful: a consumer of `EnrichmentDataUpdated` may trust that it fired because something changed.
 *
 * The mechanism's shared guards (transaction, locked re-read, state guard, operator floor) are pinned on both
 * entry points by `CatalogContentEditTest`; what is proven HERE is what the Action contributes — the i18n-map
 * diff, the in-transaction event, the audit before/after, and the no-op.
 *
 * DatabaseMigrations (mirroring `SetVariantCaseWhitelistTest` / `CatalogContentEditTest`): the mechanism opens
 * its OWN top-level `DB::transaction`, so the recorders' `transactionLevel() === 0` guard sees a real commit —
 * which `RefreshDatabase`'s wrapping transaction would suppress. Fixtures come from the spine FACTORIES, which
 * bypass the creation Actions and so record neither audit rows nor domain events: every `AuditRecord` /
 * `DomainEvent` count below is attributable to the Actions actually invoked.
 */
uses(DatabaseMigrations::class);

/**
 * A Variant standing on an `active` parent Master (the activation cascade's precondition), with its `WINE`
 * attribute set carrying exactly $notes. Named uniquely per file — Pest's top-level functions share one global
 * namespace (knowledge/testing/rules.md).
 */
function enrichmentVariant(TranslatableText $notes, LifecycleState $state = LifecycleState::Active): ProductVariant
{
    $master = ProductMaster::factory()->create(['lifecycle_state' => LifecycleState::Active]);

    $variant = ProductVariant::factory()->create([
        'product_master_id' => $master->id,
        'lifecycle_state' => $state,
    ]);

    $variant->wineAttributes()->firstOrFail()->update(['tasting_notes' => $notes]);

    return $variant;
}

/**
 * A Variant built through the REAL `CreateProductVariant` action, under its own dedicated creator operator, on
 * an `active` parent Master. The three governance tests below MUST use this rather than the factory: the
 * separation-of-duties triple reads the creator from the entity's EARLIEST `domain_events` row, and a
 * factory-built Variant has none — so the enrichment event, which lands on the same entity stream, would take
 * the creator's place and make the SoD assertions pass (or fail) for entirely the wrong reason. Nothing else in
 * this file cares who created the Variant, and the factory keeps those files' event counts attributable.
 */
function enrichmentGovernedVariant(TranslatableText $notes): ProductVariant
{
    $master = ProductMaster::factory()->create(['lifecycle_state' => LifecycleState::Active]);

    actingAs(Operator::factory()->create(), 'operator');

    return app(CreateProductVariant::class)->handle(
        productMasterId: $master->id,
        variantIdentifier: '2015',
        vintageYear: 2015,
        tastingNotes: $notes,
    );
}

/**
 * The Variant's stored tasting notes as the i18n-keyed map the audit snapshots carry.
 *
 * @return array<string, string>|null
 */
function enrichmentStoredNotes(ProductVariant $variant): ?array
{
    return ProductVariantWineAttributes::query()
        ->where('product_variant_id', $variant->id)
        ->sole()
        ->tasting_notes?->jsonSerialize();
}

/**
 * The Variant's catalog audit trail as ONE ordered list of action strings — order, per-verb counts and the
 * absence of any unexpected extra row, in a single expectation.
 *
 * @return list<string>
 */
function enrichmentAuditActions(): array
{
    return array_values(
        AuditRecord::query()
            ->orderBy('id')
            ->get()
            ->map(fn (AuditRecord $row): string => $row->action)
            ->all()
    );
}

it('records EnrichmentDataUpdated and one audit row when an active Variant\'s tasting notes change', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $variant = enrichmentVariant(TranslatableText::of(['en' => 'Cherry, cedar.']));

    $maintained = app(UpdateProductVariantEnrichment::class)->handle(
        $variant,
        TranslatableText::of(['en' => 'Cherry, cedar, leather.', 'it' => 'Ciliegia, cedro, cuoio.']),
    );

    expect($maintained->id)->toBe($variant->id)
        ->and(enrichmentStoredNotes($variant))->toEqual([
            'en' => 'Cherry, cedar, leather.',
            'it' => 'Ciliegia, cedro, cuoio.',
        ]);

    // EXACTLY one domain event — the 22nd catalog event — recorded in the mechanism's transaction with the
    // operator envelope, and a payload that is a bare Variant REFERENCE: the prose that moved never rides it.
    $event = DomainEvent::query()->sole();

    expect($event->name)->toBe('EnrichmentDataUpdated')
        ->and($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('ProductVariant')
        ->and($event->entity_id)->toBe((string) $variant->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($operator->id)
        ->and(array_keys($event->payload))->toBe(['product_variant_id'])
        ->and($event->payload)->toEqual(['product_variant_id' => $variant->id])
        ->and($event->payload)->not->toHaveKey('tasting_notes');

    // EXACTLY one audit row, carrying the before/after of the changed field. `toEqual`, never `toBe`: PostgreSQL's
    // jsonb reorders a snapshot map's keys (and the nested locale map's keys with them).
    $audit = AuditRecord::query()->sole();

    expect($audit->action)->toBe('catalog.product_variant.enrichment_updated')
        ->and($audit->module)->toBe('catalog')
        ->and($audit->entity_type)->toBe('ProductVariant')
        ->and($audit->entity_id)->toBe((string) $variant->id)
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($audit->actor_id)->toEqual($operator->id)
        ->and($audit->authorization_basis)->toBe('catalog-content-edit')
        ->and($audit->before)->toEqual(['tasting_notes' => ['en' => 'Cherry, cedar.']])
        ->and($audit->after)->toEqual(['tasting_notes' => [
            'en' => 'Cherry, cedar, leather.',
            'it' => 'Ciliegia, cedro, cuoio.',
        ]]);

    // The three negative facts of an enrichment write: `version` stands (on the row AND absent from both
    // snapshots — enrichment is not the entity's identity), and the lifecycle never moved.
    $persisted = ProductVariant::findOrFail($variant->id);

    expect($persisted->version)->toBe(1)
        ->and($persisted->lifecycle_state)->toBe(LifecycleState::Active)
        ->and($audit->before)->not->toHaveKey('version')
        ->and($audit->after)->not->toHaveKey('version');
});

it('clears the prose when the replacement is null, auditing the cleared value', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $variant = enrichmentVariant(TranslatableText::of(['en' => 'Cherry, cedar.']));

    // `null` is a legitimate value on either side of the diff — a cleared note is a real change, and the event
    // fires for it exactly as for a rewrite.
    app(UpdateProductVariantEnrichment::class)->handle($variant, null);

    expect(enrichmentStoredNotes($variant))->toBeNull()
        ->and(DomainEvent::query()->sole()->name)->toBe('EnrichmentDataUpdated')
        ->and(AuditRecord::query()->sole()->before)->toEqual(['tasting_notes' => ['en' => 'Cherry, cedar.']])
        ->and(AuditRecord::query()->sole()->after)->toEqual(['tasting_notes' => null]);
});

it('is a silent no-op when the incoming enrichment carries the stored value', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $variant = enrichmentVariant(TranslatableText::of(['en' => 'Cherry, cedar.', 'it' => 'Ciliegia, cedro.']));

    $wineBefore = ProductVariantWineAttributes::query()->where('product_variant_id', $variant->id)->sole();

    // Two shapes of "identical". The second is why the diff compares the i18n-keyed MAP and not the object: the
    // console (or a future adapter) may assemble the same content in a different locale order, and that is not a
    // change. If this comparison were an identity check, a spurious EnrichmentDataUpdated would fire.
    $identical = [
        TranslatableText::of(['en' => 'Cherry, cedar.', 'it' => 'Ciliegia, cedro.']),
        TranslatableText::of(['it' => 'Ciliegia, cedro.', 'en' => 'Cherry, cedar.']),
    ];

    foreach ($identical as $notes) {
        $maintained = app(UpdateProductVariantEnrichment::class)->handle($variant, $notes);

        expect($maintained->id)->toBe($variant->id);
    }

    $wineAfter = ProductVariantWineAttributes::query()->where('product_variant_id', $variant->id)->sole();

    // No event, no audit row, no write — not even the attribute row's `updated_at` moved. The mechanism's guards
    // and the closure's diff both ran; they simply found nothing to record.
    expect(DomainEvent::query()->count())->toBe(0)
        ->and(AuditRecord::query()->count())->toBe(0)
        ->and($wineAfter->updated_at->equalTo($wineBefore->updated_at))->toBeTrue()
        ->and($wineAfter->tasting_notes?->jsonSerialize())->toEqual(['en' => 'Cherry, cedar.', 'it' => 'Ciliegia, cedro.'])
        ->and(ProductVariant::findOrFail($variant->id)->version)->toBe(1);
});

it('updates enrichment in every editable state, never touching version', function (LifecycleState $state) {
    actingAs(Operator::factory()->create(), 'operator');

    $variant = enrichmentVariant(TranslatableText::of(['en' => 'Cherry.']), $state);

    app(UpdateProductVariantEnrichment::class)->handle($variant, TranslatableText::of(['en' => 'Cedar.']));

    // Enrichment is mutable OUTSIDE the lifecycle: `active` is an ordinary state here, not a special one — the
    // post-active correction is AC-0-EVT-8's own scenario.
    $persisted = ProductVariant::findOrFail($variant->id);

    expect($persisted->lifecycle_state)->toBe($state)
        ->and($persisted->version)->toBe(1)
        ->and(enrichmentStoredNotes($variant))->toEqual(['en' => 'Cedar.'])
        ->and(DomainEvent::query()->where('name', EnrichmentDataUpdated::NAME)->count())->toBe(1);
})->with([
    'draft' => LifecycleState::Draft,
    'reviewed' => LifecycleState::Reviewed,
    'active' => LifecycleState::Active,
]);

it('does not re-arm review: a reviewed-then-enriched Variant still activates', function () {
    // Observational edits never gate a review (design D4/D5: `enrichment_updated` ends with none of the four
    // review-freshness suffixes). Proven end-to-end through the REAL Actions: the `.enrichment_updated` row is
    // the freshest catalog audit row when the approver arrives, and it must neither block nor be mistaken for
    // a `.submitted`.
    $variant = enrichmentGovernedVariant(TranslatableText::of(['en' => 'Cherry.']));

    $reviewer = Operator::factory()->create();
    actingAs($reviewer, 'operator');
    app(SubmitProductVariantForReview::class)->handle($variant);
    app(UpdateProductVariantEnrichment::class)->handle($variant->refresh(), TranslatableText::of(['en' => 'Cedar.']));

    // A DISTINCT approver activates — no re-submit needed, because nothing the reviewer approved has changed.
    actingAs(Operator::factory()->create(), 'operator');
    $activated = app(ActivateProductVariant::class)->handle($variant->refresh());

    expect($activated->lifecycle_state)->toBe(LifecycleState::Active)
        ->and($activated->version)->toBe(1)   // neither the enrichment nor the activation is an identity edit
        ->and(DomainEvent::query()->where('name', 'ProductVariantActivated')->count())->toBe(1)
        ->and(enrichmentAuditActions())->toBe([
            'catalog.product_variant.submitted',
            'catalog.product_variant.enrichment_updated',
            'catalog.product_variant.activated',
        ]);
});

it('does not clear a pending rejection: an enrichment row never unblocks activation', function () {
    // The S1 hole, closed by the verb filter and re-proven here through the REAL enrichment Action rather than a
    // hand-written audit row: the `.enrichment_updated` row is the raw latest action on the trail, and a raw
    // latest-action read would have unblocked this activation.
    $variant = enrichmentGovernedVariant(TranslatableText::of(['en' => 'Cherry.']));

    $reviewer = Operator::factory()->create();
    actingAs($reviewer, 'operator');
    app(SubmitProductVariantForReview::class)->handle($variant);
    app(RejectProductVariantReview::class)->handle($variant->refresh(), 'Notes read like the 2014.');
    app(UpdateProductVariantEnrichment::class)->handle($variant->refresh(), TranslatableText::of(['en' => 'Cedar.']));

    actingAs(Operator::factory()->create(), 'operator');

    // The discriminating token: the block must be the REJECTION cause (`un-remediated`), never the identity-edit
    // cause (`edited`) — an enrichment update is neither, and it remediates nothing.
    expect(fn () => app(ActivateProductVariant::class)->handle($variant->refresh()))
        ->toThrow(ApprovalGovernanceViolation::class, 'un-remediated');

    expect(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'ProductVariantActivated')->count())->toBe(0);

    // Only an explicit re-submit clears it — and the enrichment row, still on the trail, does not interfere.
    actingAs($reviewer, 'operator');
    app(ResubmitProductVariantForReview::class)->handle($variant->refresh());

    actingAs(Operator::factory()->create(), 'operator');
    expect(app(ActivateProductVariant::class)->handle($variant->refresh())->lifecycle_state)
        ->toBe(LifecycleState::Active);
});

it('records EnrichmentDataUpdated only from the enrichment path, never from a lifecycle transition', function () {
    $variant = enrichmentGovernedVariant(TranslatableText::of(['en' => 'Cherry.']));

    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitProductVariantForReview::class)->handle($variant);

    actingAs(Operator::factory()->create(), 'operator');
    app(ActivateProductVariant::class)->handle($variant->refresh());
    app(UpdateProductVariantEnrichment::class)->handle($variant->refresh(), TranslatableText::of(['en' => 'Cedar.']));
    app(RetireProductVariant::class)->handle($variant->refresh());

    // The event log, in order: three lifecycle transitions around one enrichment update. `submitted` is
    // audit-only (no event), and not one of `*Created` / `*Activated` / `*Retired` carries an enrichment event
    // with it — the twenty-one lifecycle events and this one are recorded by disjoint writers. The creation
    // Action seeded `tasting_notes` without recording an EnrichmentDataUpdated, too: enrichment ARRIVES with the
    // entity, it does not "change" then.
    $events = array_values(
        DomainEvent::query()->orderBy('id')->get()->map(fn (DomainEvent $event): string => $event->name)->all()
    );

    expect($events)->toBe([
        'ProductVariantCreated',
        'ProductVariantActivated',
        'EnrichmentDataUpdated',
        'ProductVariantRetired',
    ]);
});

it('rejects an enrichment update on a retired Variant, ahead of its own no-op diff', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $notes = TranslatableText::of(['en' => 'Cherry.']);
    $variant = enrichmentVariant($notes, LifecycleState::Retired);

    // The IDENTICAL value against a `retired` Variant pins the precedence: the mechanism's state guard runs before
    // `$apply`, so the operator reads the `retired` state — the fact they can act on — and not a silent success.
    // Both would "write nothing"; only one of them is the truth about why.
    expect(fn () => app(UpdateProductVariantEnrichment::class)->handle($variant, $notes))
        ->toThrow(IllegalContentEdit::class, 'reopened');

    expect(AuditRecord::query()->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0)
        ->and(enrichmentStoredNotes($variant))->toEqual(['en' => 'Cherry.']);
});

it('rejects an enrichment update under a system actor, writing nothing', function () {
    // No actingAs(): recording an observation about a wine is an operator decision, exactly as an identity edit is.
    $variant = enrichmentVariant(TranslatableText::of(['en' => 'Cherry.']));

    expect(fn () => app(UpdateProductVariantEnrichment::class)->handle($variant, TranslatableText::of(['en' => 'Cedar.'])))
        ->toThrow(ApprovalGovernanceViolation::class);

    expect(AuditRecord::query()->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0)
        ->and(enrichmentStoredNotes($variant))->toEqual(['en' => 'Cherry.']);
});
