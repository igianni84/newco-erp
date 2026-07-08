<?php

// Task 6.3 (catalog-module-0-completeness-sweep; design D8; spec — Operator edits catalog identity content
// through the console) — the Composite SKU's ONE field-edit surface: the `editComposition` modal header action on
// the view page.
//
// The console SURFACES the domain, it reimplements none of it: `UpdateCompositeSkuComposition` owns the `retired`
// state guard, the operator floor, the `N ≥ 2 distinct` floor (BR-SKU-2), the activation cascade re-asserted at
// edit time on an `active` Composite, the `version` increment and the audited ordered before/after. What THIS file
// pins is the wiring — that the modal is prefilled with the bundle in its stored ORDER (never sorted), that a
// successful edit leaves the page's own record carrying the incremented `version`, and that every localized domain
// rejection lands as a FORM VALIDATION ERROR on the modal's `constituents` field, leaving the bundle, the version,
// the audit log and the event log untouched (the delta's "An invalid composition edit is surfaced without changing
// state").
//
// A Composite is attribute-free beyond its ordered constituent set (§ 3.8), so that set IS its identity: the edit
// records the `identity_updated` verb (design D5), re-versions, and — unlike the Variant's two MAINTENANCE modals
// (task 6.2) — re-arms review. Hence the audit action asserted below is `catalog.composite_sku.identity_updated`,
// the very same verb the Master's rename writes.
//
// DatabaseMigrations (mirroring ProductMasterIdentityEditConsoleTest): the console action drives a real domain
// action that opens its OWN DB::transaction, so the recorders' transaction-level guard sees a real commit
// (RefreshDatabase would wrap every write in a never-committed outer transaction). Catalog enums/models/actions
// are imported freely here: the {Models, Actions} import-boundary carve-out governs OperatorPanel PRODUCTION code,
// not tests. Constituent Product References come from the FACTORY, which bypasses `CreateProductReference` and so
// records neither audit rows nor domain events: every count below is attributable to an Action actually invoked.

use App\Modules\Catalog\Actions\ActivateCompositeSku;
use App\Modules\Catalog\Actions\CreateCompositeSku;
use App\Modules\Catalog\Actions\RetireCompositeSku;
use App\Modules\Catalog\Actions\SubmitCompositeSkuForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\CompositeSkuResource\Pages\ViewCompositeSku;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Audit\AuditRecord;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

/**
 * A constituent Product Reference in the given state, stood up directly through the factory (which records no
 * event and no audit row). The cascade re-assert reads only each constituent's `lifecycle_state`, so a
 * factory-built PR is a legitimate fixture. Named uniquely per file — Pest's top-level functions share one global
 * namespace (knowledge/testing/rules.md).
 */
function compositionEditReference(LifecycleState $state = LifecycleState::Active): ProductReference
{
    return ProductReference::factory()->create(['lifecycle_state' => $state]);
}

/**
 * A `draft` Composite SKU over the given ORDERED constituent ids, created through the real Catalog action as the
 * currently-acting operator.
 *
 * @param  list<int>  $referenceIds
 */
function compositionEditDraft(array $referenceIds): CompositeSku
{
    return app(CreateCompositeSku::class)->handle($referenceIds);
}

/**
 * An `active` Composite SKU over the given ORDERED constituent ids, driven through the real domain chain by three
 * DISTINCT operators (the production Creator → Reviewer → Approver floor). Leaves `$approver` acting — any
 * authenticated operator may edit content, so no further SoD step is needed.
 *
 * @param  list<int>  $referenceIds
 */
function compositionEditActive(Operator $creator, Operator $reviewer, Operator $approver, array $referenceIds): CompositeSku
{
    actingAs($creator, 'operator');
    $composite = compositionEditDraft($referenceIds);

    actingAs($reviewer, 'operator');
    app(SubmitCompositeSkuForReview::class)->handle($composite);

    actingAs($approver, 'operator');
    app(ActivateCompositeSku::class)->handle($composite);

    return $composite;
}

/**
 * The record instance the PAGE holds after its last request — the very object the infolist renders, not a re-query
 * of the row. Narrowed by an `instanceof` guard (the production `recordOf()` idiom) rather than a cast, so the
 * assertion that reads `version` off it is typed all the way down.
 *
 * @param  Testable<ViewCompositeSku>  $page
 */
function compositionEditPageRecord(Testable $page): CompositeSku
{
    $record = $page->instance()->getRecord();

    if (! $record instanceof CompositeSku) {
        throw new LogicException('The Livewire page under test does not hold a Composite SKU.');
    }

    return $record;
}

/**
 * The Composite's constituent ids as stored, in bundle `position` order (the relation carries the `orderByPivot`).
 * `array_values` re-indexes the Eloquent collection's keys, which PHPStan cannot otherwise prove contiguous — and a
 * `list` is what an ORDERED, index-compared assertion needs (the Action's own read takes the same care).
 *
 * @return list<int>
 */
function compositionEditStoredIds(CompositeSku $composite): array
{
    return array_values(
        CompositeSku::findOrFail($composite->id)
            ->constituents()
            ->get()
            ->map(fn (ProductReference $constituent): int => $constituent->id)
            ->all()
    );
}

/**
 * An `assertHasActionErrors` matcher asserting the field carries EXACTLY this localized domain message.
 *
 * The plain `['constituents' => $message]` overload cannot express it. Livewire's
 * `assertErrorMatchesRuleOrMessage()` first does `Str::before($expected, ':')` — so that `min:3` can be written as
 * a rule — and one of this modal's three domain messages ("Cannot change the composition of this CompositeSku:
 * it is active, …") CONTAINS a colon. The truncated needle then never matches the whole message, and the failure
 * reads as a missing error rather than a mangled expectation. Livewire's Closure overload is handed the raw
 * message list instead, and compares it whole; the inner `expect()` keeps the diff readable.
 *
 * @return Closure(array<int, string>, array<int, string>): bool
 */
function compositionEditRejectsWith(string $message): Closure
{
    return function (array $failedRules, array $messages) use ($message): bool {
        expect($messages)->toContain($message);

        return true;
    };
}

it('exposes an edit-composition action prefilled with the current constituents in bundle order', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $first = compositionEditReference();
    $second = compositionEditReference();

    // Bundled in REVERSE id order, so the prefill assertion proves the modal opens on the STORED ORDER. A prefill
    // that sorted (or that read the un-pivoted relation) would hand back ascending ids and red here — and then an
    // operator who merely opened and saved would silently REORDER the bundle, which is a real content change.
    $composite = compositionEditDraft([$second->id, $first->id]);

    Livewire::test(ViewCompositeSku::class, ['record' => $composite->getKey()])
        ->mountAction('editComposition')
        ->assertActionDataSet(['constituents' => [$second->id, $first->id]]);
});

it('replaces an active Composite\'s composition through the console, incrementing version and recording one audit row with no domain event', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $incumbent = compositionEditReference();
    $dropped = compositionEditReference();
    $added = compositionEditReference();

    $composite = compositionEditActive($creator, $reviewer, $approver, [$incumbent->id, $dropped->id]);
    expect(CompositeSku::findOrFail($composite->id)->version)->toBe(1);

    $eventsBeforeEdit = DomainEvent::query()->count();

    // The BR-Audit-1 AUTO scenario's Composite half through the console: an `active` bundle is re-composed — one
    // constituent dropped, one added, and the survivor moved to position 2 (the replacement set is ordered content).
    $page = Livewire::test(ViewCompositeSku::class, ['record' => $composite->getKey()]);

    $page->callAction('editComposition', ['constituents' => [$added->id, $incumbent->id]])
        ->assertHasNoActionErrors()
        ->assertNotified((string) __('operator_console.composite_sku.notifications.composition_updated'));

    // In-place re-versioning: version 1 → 2, the bundle is exactly the replacement set in the submitted order, and
    // the Composite stays `active` (the FSM has no active → reviewed edge).
    expect(compositionEditStoredIds($composite))->toBe([$added->id, $incumbent->id])
        ->and(CompositeSku::findOrFail($composite->id)->version)->toBe(2)
        ->and(CompositeSku::findOrFail($composite->id)->lifecycle_state)->toBe(LifecycleState::Active);

    // The VIEW reflects the increment (spec: "the View SHALL reflect the incremented `version`"). Read off the
    // PAGE's record, not the DB: the Action mutates the very instance the page handed it. An Action that wrote a
    // COPY would leave this at 1.
    expect(compositionEditPageRecord($page)->version)->toBe(2);

    // Exactly one identity-edit audit row carrying the operator envelope; the OLD bundle is retrievable from it
    // (BR-Audit-1's "old version retrievable" for an entity whose content lives wholly in a join table).
    $audit = AuditRecord::query()->where('action', 'catalog.composite_sku.identity_updated')->sole();

    expect($audit->module)->toBe('catalog')
        ->and($audit->entity_type)->toBe('CompositeSku')
        ->and($audit->entity_id)->toBe((string) $composite->id)
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($audit->actor_id)->toEqual($approver->id)
        // `toEqual`, never `toBe`: a decoded jsonb snapshot carries the ENGINE's own scalar types.
        ->and($audit->before)->toEqual(['constituents' => [$incumbent->id, $dropped->id], 'version' => 1])
        ->and($audit->after)->toEqual(['constituents' => [$added->id, $incumbent->id], 'version' => 2]);

    // Event-silent: an identity edit records NO domain event (design D2 — the event surface is unchanged).
    expect(DomainEvent::query()->count())->toBe($eventsBeforeEdit);
});

it('surfaces the N ≥ 2 distinct-constituent floor as a validation error on the modal, changing nothing', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $first = compositionEditReference();
    $second = compositionEditReference();
    $composite = compositionEditActive($creator, $reviewer, $approver, [$first->id, $second->id]);

    $eventsBeforeEdit = DomainEvent::query()->count();

    // ONE constituent — an operator DESELECTING one of the two. The picker's `required()` rule refuses only an
    // EMPTY selection, so this reaches the domain's BR-SKU-2 floor: the console expresses no count rule at all.
    //
    // The deselection cannot travel through `callAction($name, $data)`. That path fills the mounted form via
    // `Arr::dot()` + `data_set()`, i.e. index-by-index, and its `unsetMissingNumericArrayKeys()` prune — the thing
    // that would drop the now-missing index 1 — builds a `$currentStatePath` that never matches the mounted
    // action's real state path, so it prunes nothing. A 2 → 1 submission would silently arrive as the UNCHANGED
    // 2-element bundle and pass. Mounting, `set()`ting the whole list (one `data_set` on the array itself) and
    // then calling the mounted action is the shrink the operator's multi-select actually performs.
    Livewire::test(ViewCompositeSku::class, ['record' => $composite->getKey()])
        ->mountAction('editComposition')
        ->set('mountedActions.0.data.constituents', [$first->id])
        ->callMountedAction()
        ->assertHasActionErrors(['constituents' => compositionEditRejectsWith(
            (string) __('catalog.composite_sku.insufficient_constituents', ['count' => 1]),
        )]);

    // Rejected inside the mechanism's transaction, before any write to the join table.
    expect(compositionEditStoredIds($composite))->toBe([$first->id, $second->id])
        ->and(CompositeSku::findOrFail($composite->id)->version)->toBe(1)
        ->and(AuditRecord::query()->where('action', 'catalog.composite_sku.identity_updated')->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe($eventsBeforeEdit);
});

it('surfaces the edit-time cascade re-assert when an active Composite is given a non-active constituent, changing nothing', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $incumbent = compositionEditReference();
    $second = compositionEditReference();
    $draftReference = compositionEditReference(LifecycleState::Draft);

    $composite = compositionEditActive($creator, $reviewer, $approver, [$incumbent->id, $second->id]);

    $eventsBeforeEdit = DomainEvent::query()->count();

    // The delta scenario: an `active` Composite may never come to reference a non-`active` constituent through the
    // back door of an edit (`ActivateCompositeSku`'s gate never runs again on an already-active entity). The
    // console holds no cascade code — the rejection is the Action's, rendered verbatim under the field.
    Livewire::test(ViewCompositeSku::class, ['record' => $composite->getKey()])
        ->assertActionVisible('editComposition')
        ->callAction('editComposition', ['constituents' => [$incumbent->id, $draftReference->id]])
        ->assertHasActionErrors(['constituents' => compositionEditRejectsWith(
            (string) __('catalog.gate.parent_not_active_on_composition_edit', [
                'entity' => 'CompositeSku',
                'parent' => 'ProductReference',
            ]),
        )]);

    // Constituents, version, audit log and event log all unchanged.
    expect(compositionEditStoredIds($composite))->toBe([$incumbent->id, $second->id])
        ->and(CompositeSku::findOrFail($composite->id)->version)->toBe(1)
        ->and(CompositeSku::findOrFail($composite->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(AuditRecord::query()->where('action', 'catalog.composite_sku.identity_updated')->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe($eventsBeforeEdit);
});

it('arms the re-submit button on a reviewed Composite once its composition is edited through the console', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();

    $incumbent = compositionEditReference();
    $dropped = compositionEditReference();
    $added = compositionEditReference();

    actingAs($creator, 'operator');
    $composite = compositionEditDraft([$incumbent->id, $dropped->id]);
    actingAs($reviewer, 'operator');
    app(SubmitCompositeSkuForReview::class)->handle($composite);

    // Freshly reviewed and never rejected: nothing to re-submit, so the button is hidden.
    Livewire::test(ViewCompositeSku::class, ['record' => $composite->getKey()])
        ->assertActionHidden('resubmit');

    // The composition edit writes `identity_updated` — one of the four review-freshness suffixes (design D5) —
    // because a Composite's constituent set IS the content its reviewer approved. The `reviewed` state has no
    // constituent-state condition (the cascade applies at activation), so the edit lands.
    actingAs($creator, 'operator');
    Livewire::test(ViewCompositeSku::class, ['record' => $composite->getKey()])
        ->callAction('editComposition', ['constituents' => [$incumbent->id, $added->id]])
        ->assertHasNoActionErrors();

    // What the reviewer approved has changed, so the entity is REVIEW-STALE: the console offers the re-arm, and
    // the domain would refuse an activation until it is taken. This is the visible half of that one fact — the
    // maintenance modals of task 6.2 (`enrichment_updated`, `whitelist_updated`) deliberately arm nothing.
    Livewire::test(ViewCompositeSku::class, ['record' => $composite->getKey()])
        ->assertActionVisible('resubmit');

    expect(CompositeSku::findOrFail($composite->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(CompositeSku::findOrFail($composite->id)->version)->toBe(2);
});

it('surfaces the retired-state guard on the composition modal, leaving the retired Composite untouched', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $first = compositionEditReference();
    $second = compositionEditReference();
    $spare = compositionEditReference();

    $composite = compositionEditActive($creator, $reviewer, $approver, [$first->id, $second->id]);
    app(RetireCompositeSku::class)->handle($composite);
    expect(CompositeSku::findOrFail($composite->id)->lifecycle_state)->toBe(LifecycleState::Retired);

    // The action stays VISIBLE on a retired Composite — the console hides no write behind a state pre-check
    // (design L4: surface, don't reimplement; only re-submit is visibility-gated, off a DERIVED read). The domain
    // rejects, and the rejection lands on the modal's field exactly as the two composition rejections do.
    Livewire::test(ViewCompositeSku::class, ['record' => $composite->getKey()])
        ->assertActionVisible('editComposition')
        ->callAction('editComposition', ['constituents' => [$first->id, $spare->id]])
        // `entity` is the mechanism's entity-type label (`CompositeSku`), the same token the audit action string
        // carries — not a display name. The message is the DOMAIN's, rendered verbatim under the field.
        ->assertHasActionErrors(['constituents' => (string) __('catalog.edit.cannot_edit', [
            'entity' => 'CompositeSku',
            'state' => LifecycleState::Retired->value,
        ])]);

    expect(compositionEditStoredIds($composite))->toBe([$first->id, $second->id])
        ->and(CompositeSku::findOrFail($composite->id)->version)->toBe(1)
        ->and(AuditRecord::query()->where('action', 'catalog.composite_sku.identity_updated')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| i18n (R5 — every new CONSOLE key is authored EN + IT in the task that ships its surface; invariant 12)
|--------------------------------------------------------------------------
*/

it('localizes the composition-edit label and success notification in EN and IT', function (string $key) {
    app()->setLocale('en');
    $en = (string) __($key);
    app()->setLocale('it');
    $it = (string) __($key);

    // Both locales resolve the key (not the raw key) and the IT copy is a genuine translation.
    expect($en)->not->toBe($key)
        ->and($it)->not->toBe($key)
        ->and($it)->not->toBe($en);
})->with([
    'operator_console.composite_sku.actions.edit_composition',
    'operator_console.composite_sku.notifications.composition_updated',
]);
