<?php

use App\Modules\Catalog\Actions\ActivateProductVariant;
use App\Modules\Catalog\Actions\SetVariantCaseWhitelist;
use App\Modules\Catalog\Actions\SubmitProductVariantForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalContentEdit;
use App\Modules\Catalog\Exceptions\UnknownCatalogReference;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\VariantCaseWhitelistEntry;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Audit\AuditRecord;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;

use function Pest\Laravel\actingAs;

/**
 * Pins `SetVariantCaseWhitelist` — the Layer-1 whitelist's sole writer, and the FIRST Action on the mechanism's
 * non-versioning `maintain()` entry point (catalog-module-0-completeness-sweep task 3.1; design D6;
 * product-catalog — Requirement: Layer-1 Case-Configuration Whitelist; Module 0 PRD § 3.3 + § 7.1;
 * AC-0-J-13's maintenance half, AC-0-XM-11).
 *
 * The whitelist is not the Variant's identity, so the four NEGATIVE facts are as load-bearing as the positive
 * one and are asserted on every happy path: no `version` change, no domain event, no re-arm of review, and no
 * effect on the Variant's other formats. The mechanism's shared guards (transaction, locked re-read, state
 * guard, operator floor) are pinned by `CatalogContentEditTest` on BOTH entry points; what is proven HERE is
 * what the Action contributes — the per-pair replacement delta, the reference re-checks that convert the pivot's
 * RESTRICT foreign keys into a domain rejection, and the before/after admitted sets in the audit row.
 *
 * The ENFORCEMENT half of the whitelist (the Sellable-SKU activation gate, AC-0-J-13's core case) is task 3.2;
 * nothing here reads the pivot back except this Action itself.
 *
 * DatabaseMigrations (mirroring `CatalogContentEditTest` / `UpdateCompositeSkuCompositionTest`): the mechanism
 * opens its OWN top-level `DB::transaction`, so the audit recorder's `transactionLevel() === 0` guard sees a real
 * commit — which `RefreshDatabase`'s wrapping transaction would suppress. Fixtures come from the spine FACTORIES,
 * which bypass the creation Actions and so record neither audit rows nor domain events: every `AuditRecord` /
 * `DomainEvent` count below is attributable to the Actions actually invoked.
 */
uses(DatabaseMigrations::class);

/**
 * Persist one admitted triple directly (the fixture shape of a pre-existing whitelist). Named uniquely per file
 * — Pest's top-level functions share one global namespace (knowledge/testing/rules.md).
 */
function whitelistFixtureEntry(ProductVariant $variant, Format $format, CaseConfiguration $caseConfiguration): VariantCaseWhitelistEntry
{
    return VariantCaseWhitelistEntry::create([
        'product_variant_id' => $variant->id,
        'format_id' => $format->id,
        'case_configuration_id' => $caseConfiguration->id,
    ]);
}

/**
 * The pair's currently admitted Case-Configuration ids, ascending — the property every assertion below reads.
 * Hydrated rather than `pluck()`ed (which is `mixed` to static analysis), and `array_values`-wrapped: a
 * `Collection::map()->all()` is never a `list` to PHPStan, and `toBe()` on a snapshot compares keys too.
 *
 * @return list<int>
 */
function whitelistAdmittedIds(ProductVariant $variant, Format $format): array
{
    return array_values(
        VariantCaseWhitelistEntry::query()
            ->where('product_variant_id', $variant->id)
            ->where('format_id', $format->id)
            ->orderBy('case_configuration_id')
            ->get()
            ->map(fn (VariantCaseWhitelistEntry $entry): int => $entry->case_configuration_id)
            ->all()
    );
}

it('replaces a pair\'s admitted set in one call, auditing the before/after sets', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $variant = ProductVariant::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $format = Format::factory()->create();
    [$owc6, $carton12, $loose] = CaseConfiguration::factory()->count(3)->create()->all();

    // The J-13 fixture: three admitted configurations on an `active` Variant.
    whitelistFixtureEntry($variant, $format, $owc6);
    whitelistFixtureEntry($variant, $format, $carton12);
    whitelistFixtureEntry($variant, $format, $loose);

    // ONE call both REMOVES owc6 and ADDS a fourth — replacement semantics, not a patch.
    $newcomer = CaseConfiguration::factory()->create();

    $maintained = app(SetVariantCaseWhitelist::class)->handle(
        $variant,
        $format->id,
        [$loose->id, $newcomer->id, $carton12->id],   // deliberately unsorted: the set is canonicalised
    );

    $expected = collect([$carton12->id, $loose->id, $newcomer->id])->sort()->values()->all();

    expect(whitelistAdmittedIds($variant, $format))->toBe($expected)
        ->and($maintained->id)->toBe($variant->id);

    // Exactly ONE audit row, carrying the PAIR and its before/after admitted sets — canonical ascending order,
    // so the same set always audits as the same list whatever order the console sent.
    $audit = AuditRecord::query()->sole();

    $before = collect([$owc6->id, $carton12->id, $loose->id])->sort()->values()->all();

    expect($audit->action)->toBe('catalog.product_variant.whitelist_updated')
        ->and($audit->module)->toBe('catalog')
        ->and($audit->entity_type)->toBe('ProductVariant')
        ->and($audit->entity_id)->toBe((string) $variant->id)
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($audit->actor_id)->toEqual($operator->id)
        ->and($audit->authorization_basis)->toBe('catalog-content-edit')
        // toEqual, never toBe: PostgreSQL's jsonb reorders a snapshot MAP's keys — but still compares the nested
        // admitted LIST element-wise by index, which is what a canonical ordering is for.
        ->and($audit->before)->toEqual(['format_id' => $format->id, 'case_configurations' => $before])
        ->and($audit->after)->toEqual(['format_id' => $format->id, 'case_configurations' => $expected]);

    // The three negative facts of a maintenance write: `version` stands, the row is untouched, no event.
    $persisted = ProductVariant::findOrFail($variant->id);

    expect($persisted->version)->toBe(1)
        ->and($persisted->lifecycle_state)->toBe(LifecycleState::Active)
        ->and($audit->before)->not->toHaveKey('version')
        ->and($audit->after)->not->toHaveKey('version')
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('clears a pair with an empty set, restoring the permissive default', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $variant = ProductVariant::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $format = Format::factory()->create();
    $caseConfiguration = CaseConfiguration::factory()->create();

    whitelistFixtureEntry($variant, $format, $caseConfiguration);

    app(SetVariantCaseWhitelist::class)->handle($variant, $format->id, []);

    // Absence admits, presence narrows (§ 7.1): clearing the pair is a legitimate call, not an under-floor one.
    expect(whitelistAdmittedIds($variant, $format))->toBe([])
        ->and(AuditRecord::query()->sole()->after)->toEqual(['format_id' => $format->id, 'case_configurations' => []])
        ->and(ProductVariant::findOrFail($variant->id)->version)->toBe(1);
});

it('scopes the replacement to the pair, leaving the Variant\'s other formats untouched', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $variant = ProductVariant::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    [$magnum, $bottle] = Format::factory()->count(2)->create()->all();
    [$owc6, $carton12] = CaseConfiguration::factory()->count(2)->create()->all();

    // The SAME configuration is admitted for both formats — which the pivot's unique TRIPLE allows and its
    // per-(Variant, Format) scoping exists to express (design D6).
    whitelistFixtureEntry($variant, $magnum, $owc6);
    whitelistFixtureEntry($variant, $bottle, $owc6);
    whitelistFixtureEntry($variant, $bottle, $carton12);

    // Emptying the magnum pair must not reach the bottle pair's rows — nor its identical `owc6` entry.
    app(SetVariantCaseWhitelist::class)->handle($variant, $magnum->id, []);

    expect(whitelistAdmittedIds($variant, $magnum))->toBe([])
        ->and(whitelistAdmittedIds($variant, $bottle))->toBe(collect([$owc6->id, $carton12->id])->sort()->values()->all());
});

it('replaces as a delta, preserving a surviving entry\'s row', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $variant = ProductVariant::factory()->create();
    $format = Format::factory()->create();
    [$survivor, $dropped, $newcomer] = CaseConfiguration::factory()->count(3)->create()->all();

    $survivorEntry = whitelistFixtureEntry($variant, $format, $survivor);
    whitelistFixtureEntry($variant, $format, $dropped);

    app(SetVariantCaseWhitelist::class)->handle($variant, $format->id, [$survivor->id, $newcomer->id]);

    // Only the dropped row is deleted and only the newcomer inserted: the survivor keeps its identity — and with
    // it its `created_at`, the answer to "since when has this packaging been admitted?".
    $persistedSurvivor = VariantCaseWhitelistEntry::query()
        ->where('product_variant_id', $variant->id)
        ->where('case_configuration_id', $survivor->id)
        ->sole();

    expect($persistedSurvivor->id)->toBe($survivorEntry->id)
        ->and($persistedSurvivor->created_at->equalTo($survivorEntry->created_at))->toBeTrue()
        ->and(whitelistAdmittedIds($variant, $format))->toBe(collect([$survivor->id, $newcomer->id])->sort()->values()->all());
});

it('collapses duplicate ids in the supplied set', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $variant = ProductVariant::factory()->create();
    $format = Format::factory()->create();
    $caseConfiguration = CaseConfiguration::factory()->create();

    // A SET: the pivot's unique triple would refuse the second insert as a constraint violation, so the Action
    // normalises before it writes.
    app(SetVariantCaseWhitelist::class)->handle($variant, $format->id, [$caseConfiguration->id, $caseConfiguration->id]);

    expect(whitelistAdmittedIds($variant, $format))->toBe([$caseConfiguration->id])
        ->and(AuditRecord::query()->sole()->after)->toEqual([
            'format_id' => $format->id,
            'case_configurations' => [$caseConfiguration->id],
        ]);
});

it('rejects an unknown Format, writing nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $variant = ProductVariant::factory()->create();
    $caseConfiguration = CaseConfiguration::factory()->create();

    // The pivot's `format_id` FK would raise a driver error; the domain names the offending id instead.
    expect(fn () => app(SetVariantCaseWhitelist::class)->handle($variant, 9_999, [$caseConfiguration->id]))
        ->toThrow(UnknownCatalogReference::class, 'Format');

    expect(VariantCaseWhitelistEntry::query()->count())->toBe(0)
        ->and(AuditRecord::query()->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('rejects an unknown Case Configuration, naming only the missing id and writing nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $variant = ProductVariant::factory()->create();
    $format = Format::factory()->create();
    $known = CaseConfiguration::factory()->create();

    // A pre-existing entry proves the rejection rolls back BEFORE the delta touches the pivot: the incumbent set
    // is intact afterwards, even though the call would have dropped it.
    whitelistFixtureEntry($variant, $format, $known);

    $rejection = null;

    try {
        app(SetVariantCaseWhitelist::class)->handle($variant, $format->id, [$known->id, 8_888]);
    } catch (UnknownCatalogReference $exception) {
        $rejection = $exception;
    }

    // Only the id that resolved to nothing is named — a single stale id in a set names itself, never the whole
    // input (the `$known` id must be absent from the reason).
    expect($rejection)->toBeInstanceOf(UnknownCatalogReference::class)
        ->and($rejection?->getMessage())->toContain('CaseConfiguration')
        ->and($rejection?->getMessage())->toContain('8888')
        ->and($rejection?->getMessage())->not->toContain((string) $known->id);

    expect(whitelistAdmittedIds($variant, $format))->toBe([$known->id])
        ->and(AuditRecord::query()->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('rejects whitelist maintenance on a retired Variant, ahead of its own reference re-checks', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $variant = ProductVariant::factory()->create(['lifecycle_state' => LifecycleState::Retired]);
    $format = Format::factory()->create();

    // A DELIBERATELY invalid payload (an unknown Case Configuration) against a `retired` Variant pins the
    // precedence: the mechanism's state guard runs before `$apply`, so the operator reads the `retired` state —
    // the fact they can act on — and not "id 7777 does not exist". Both would "reject"; only one is the reason.
    expect(fn () => app(SetVariantCaseWhitelist::class)->handle($variant, $format->id, [7_777]))
        ->toThrow(IllegalContentEdit::class, 'reopened');

    expect(VariantCaseWhitelistEntry::query()->count())->toBe(0)
        ->and(AuditRecord::query()->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('rejects whitelist maintenance under a system actor, writing nothing', function () {
    // No actingAs(): maintaining a whitelist is an operator decision, exactly as an identity edit is.
    $variant = ProductVariant::factory()->create();
    $format = Format::factory()->create();
    $caseConfiguration = CaseConfiguration::factory()->create();

    expect(fn () => app(SetVariantCaseWhitelist::class)->handle($variant, $format->id, [$caseConfiguration->id]))
        ->toThrow(ApprovalGovernanceViolation::class);

    expect(VariantCaseWhitelistEntry::query()->count())->toBe(0)
        ->and(AuditRecord::query()->count())->toBe(0);
});

it('maintains a whitelist in every editable state', function (LifecycleState $state) {
    actingAs(Operator::factory()->create(), 'operator');

    $variant = ProductVariant::factory()->create(['lifecycle_state' => $state]);
    $format = Format::factory()->create();
    $caseConfiguration = CaseConfiguration::factory()->create();

    app(SetVariantCaseWhitelist::class)->handle($variant, $format->id, [$caseConfiguration->id]);

    // Reductions on an `active` Variant are the J-13 core case, so `active` is an ordinary state here.
    expect(whitelistAdmittedIds($variant, $format))->toBe([$caseConfiguration->id])
        ->and(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe($state)
        ->and(ProductVariant::findOrFail($variant->id)->version)->toBe(1);
})->with([
    'draft' => LifecycleState::Draft,
    'reviewed' => LifecycleState::Reviewed,
    'active' => LifecycleState::Active,
]);

it('does not re-arm review: a reviewed-then-whitelisted Variant still activates', function () {
    // The whitelist is neither review-governed identity content nor observational enrichment, so its audit verb
    // is invisible to the review-freshness derivation (design D4/D5). Proven end-to-end through the REAL
    // Actions: the `.whitelist_updated` row is the freshest catalog audit row on the Variant when the approver
    // arrives, and it must neither block nor be mistaken for a `.submitted`.
    $master = ProductMaster::factory()->create(['lifecycle_state' => LifecycleState::Active]);
    $variant = ProductVariant::factory()->create(['product_master_id' => $master->id]);
    $format = Format::factory()->create();
    $caseConfiguration = CaseConfiguration::factory()->create();

    // The reviewer submits (recording `.submitted`), then maintains the whitelist (recording `.whitelist_updated`).
    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitProductVariantForReview::class)->handle($variant);
    app(SetVariantCaseWhitelist::class)->handle($variant, $format->id, [$caseConfiguration->id]);

    expect(AuditRecord::query()->orderByDesc('id')->firstOrFail()->action)
        ->toBe('catalog.product_variant.whitelist_updated');

    // A DISTINCT approver activates — no re-submit needed, because nothing the reviewer approved has changed.
    actingAs(Operator::factory()->create(), 'operator');
    $activated = app(ActivateProductVariant::class)->handle($variant->refresh());

    expect($activated->lifecycle_state)->toBe(LifecycleState::Active)
        ->and($activated->version)->toBe(1)   // neither the whitelist nor the activation is a content edit
        ->and(DomainEvent::query()->where('name', 'ProductVariantActivated')->count())->toBe(1)
        ->and(AuditRecord::query()->orderBy('id')->get()->map(fn (AuditRecord $row): string => $row->action)->all())
        ->toBe([
            'catalog.product_variant.submitted',
            'catalog.product_variant.whitelist_updated',
            'catalog.product_variant.activated',
        ]);
});
