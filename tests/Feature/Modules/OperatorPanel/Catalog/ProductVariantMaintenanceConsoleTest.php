<?php

// Task 6.2 (catalog-module-0-completeness-sweep; design D6/D8/D11; spec — Operator maintains Variant enrichment
// and the Layer-1 whitelist through the console) — the Product Variant's TWO maintenance surfaces: the
// `editEnrichment` and `manageWhitelist` modal header actions on the view page.
//
// The console SURFACES the domain, it reimplements none of it: `UpdateProductVariantEnrichment` owns the
// i18n-map diff, the no-op rule and the `EnrichmentDataUpdated` event; `SetVariantCaseWhitelist` owns the
// per-pair replacement delta and the audit before/after; `ActivateSellableSku` owns the whitelist gate. What
// THIS file pins is the wiring — and, above all, the three NEGATIVE facts that make these MAINTENANCE rather
// than identity edits: `version` never moves, no domain event escapes the whitelist write, and the SKU gate is
// consulted only by the domain (a console attempt against a de-admitted Case Configuration comes back as the
// domain's own localized message, in the danger notification's BODY).
//
// The manage-whitelist modal is the first console modal with TWO operands. The Format is the operand that
// selects WHICH admitted set the second one replaces, so it re-prefills on change — asserted here, because a
// broken re-prefill would silently rewrite the wrong pair with the previous pair's set and no test of the
// submit path would notice.
//
// DatabaseMigrations (mirroring ProductMasterIdentityEditConsoleTest): the console action drives a real domain
// action that opens its OWN DB::transaction, so the recorders' transaction-level guard sees a real commit
// (RefreshDatabase would wrap every write in a never-committed outer transaction). Catalog enums/models/actions
// are imported freely here: the {Models, Actions} import-boundary carve-out governs OperatorPanel PRODUCTION
// code, not tests. Spine fixtures come from the FACTORIES, which bypass the creation Actions and so record
// neither audit rows nor domain events: every count below is attributable to an Action actually invoked.

use App\Modules\Catalog\Actions\CreateSellableSku;
use App\Modules\Catalog\Actions\RetireProductVariant;
use App\Modules\Catalog\Actions\SubmitSellableSkuForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\SellableSku;
use App\Modules\Catalog\Models\VariantCaseWhitelistEntry;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductVariantResource\Pages\ViewProductVariant;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\SellableSkuResource\Pages\ViewSellableSku;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Audit\AuditRecord;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use App\Platform\I18n\TranslatableText;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

/**
 * An `active` Variant under an `active` Master, carrying $notes on its 1:1 WINE attribute set. Named uniquely per
 * file — Pest's top-level functions share one global namespace (knowledge/testing/rules.md).
 */
function maintenanceConsoleVariant(?string $notes = 'Graphite and cassis.', LifecycleState $state = LifecycleState::Active): ProductVariant
{
    $master = ProductMaster::factory()->create(['lifecycle_state' => LifecycleState::Active]);

    $variant = ProductVariant::factory()->create([
        'product_master_id' => $master->id,
        'lifecycle_state' => $state,
    ]);

    $variant->wineAttributes()->firstOrFail()->update([
        'tasting_notes' => $notes === null ? null : TranslatableText::of(['en' => $notes]),
    ]);

    return $variant;
}

/**
 * The whitelist fixture: an `active` Variant + `active` Format + the `active` Product Reference over the pair,
 * and two `active` Case Configurations — the J-13 shape, one admitted and one not.
 *
 * @return array{ProductVariant, Format, ProductReference, CaseConfiguration, CaseConfiguration}
 */
function maintenanceConsoleWhitelistFixture(): array
{
    $variant = maintenanceConsoleVariant();
    $format = Format::factory()->create(['lifecycle_state' => LifecycleState::Active]);

    $reference = ProductReference::factory()->create([
        'product_variant_id' => $variant->id,
        'format_id' => $format->id,
        'lifecycle_state' => LifecycleState::Active,
    ]);

    [$owc6, $carton12] = CaseConfiguration::factory()->count(2)->create(['lifecycle_state' => LifecycleState::Active])->all();

    return [$variant, $format, $reference, $owc6, $carton12];
}

/** Persist one admitted triple directly — the fixture shape of a pre-existing whitelist. */
function maintenanceConsoleAdmit(ProductVariant $variant, Format $format, CaseConfiguration $caseConfiguration): void
{
    VariantCaseWhitelistEntry::create([
        'product_variant_id' => $variant->id,
        'format_id' => $format->id,
        'case_configuration_id' => $caseConfiguration->id,
    ]);
}

/**
 * The record instance the PAGE holds after its last request — the very object the infolist renders, not a re-query
 * of the row. Narrowed by an `instanceof` guard (the production `recordOf()` idiom) rather than a cast.
 *
 * @param  Testable<ViewProductVariant>  $page
 */
function maintenanceConsolePageRecord(Testable $page): ProductVariant
{
    $record = $page->instance()->getRecord();

    if (! $record instanceof ProductVariant) {
        throw new LogicException('The Livewire page under test does not hold a Product Variant.');
    }

    return $record;
}

/**
 * The `title => body` pairs of the notifications the LAST Livewire request sent.
 *
 * Filament's own `assertNotified()` cannot express this test's claim, on two counts. It matches a STRING argument
 * against the title only — and the title of every domain rejection is the shared `action_failed`, while the fact
 * the delta requires ("surfaced as a notification NAMING the whitelist condition") lives in the BODY. Passing it a
 * `Notification` instead matches by the notification's RANDOM id, so that overload cannot express it either.
 *
 * Two session mechanics govern the read, and both are easy to get wrong. `Notification::send()` pushes onto
 * `filament.notifications`, but on every Livewire `dehydrate` the notifications service provider MOVES that array
 * onto `filament.claimed_notifications` (a `put`, not a push — so each request OVERWRITES the previous request's
 * claim). And `Notification::assertNotified()` mounts the notifications component, whose `mount()` PULLS the
 * claimed key — so it is destructive, and a snapshot taken after it is always empty.
 *
 * Hence: read the claimed key first (falling back to the unclaimed one for a send outside a Livewire request), and
 * read it BEFORE any `assertNotified()`. The test below therefore asserts the title from this snapshot too, rather
 * than pairing it with an `assertNotified()` that would eat the evidence.
 *
 * @return array<string, string>
 */
function maintenanceConsoleNotifications(): array
{
    /** @var mixed $sent */
    $sent = session()->get('filament.claimed_notifications')
        ?? session()->get('filament.notifications', []);

    if (! is_array($sent)) {
        return [];
    }

    $notifications = [];
    foreach ($sent as $notification) {
        if (! is_array($notification)) {
            continue;
        }

        $title = $notification['title'] ?? null;
        $body = $notification['body'] ?? null;

        if (is_string($title)) {
            $notifications[$title] = is_string($body) ? $body : '';
        }
    }

    return $notifications;
}

/*
|--------------------------------------------------------------------------
| editEnrichment (delta — Enrichment is updated on an active Variant through the console)
|--------------------------------------------------------------------------
*/

it('exposes an edit-enrichment action prefilled with the Variant\'s current tasting notes', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $variant = maintenanceConsoleVariant();

    // The modal opens on the ENGLISH baseline — the sole locale the create form authors, and the one the Action
    // writes back (a multi-locale prose surface is a deferred seam).
    Livewire::test(ViewProductVariant::class, ['record' => $variant->getKey()])
        ->mountAction('editEnrichment')
        ->assertActionDataSet(['tasting_notes' => 'Graphite and cassis.']);
});

it('updates an active Variant\'s enrichment through the console, recording one event and one audit row without moving version', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $variant = maintenanceConsoleVariant();
    expect(ProductVariant::findOrFail($variant->id)->version)->toBe(1);

    $page = Livewire::test(ViewProductVariant::class, ['record' => $variant->getKey()]);

    $page->callAction('editEnrichment', ['tasting_notes' => 'Cassis, graphite and violets.'])
        ->assertHasNoActionErrors()
        ->assertNotified((string) __('operator_console.product_variant.notifications.enrichment_updated'))
        // The infolist re-rendered on the edited prose — not on the value the page mounted with.
        ->assertSee('Cassis, graphite and violets.');

    // Enrichment is NOT the Variant's identity: `version` stands and the FSM never moves (design D5/D11).
    $edited = ProductVariant::findOrFail($variant->id);
    expect($edited->version)->toBe(1)
        ->and($edited->lifecycle_state)->toBe(LifecycleState::Active)
        ->and($edited->wineAttributes?->tasting_notes?->resolve('en'))->toBe('Cassis, graphite and violets.');

    // The page's OWN record is the one the Action locked and wrote (the kit needs no refresh).
    expect(maintenanceConsolePageRecord($page)->version)->toBe(1);

    // EVT-8 through the console: exactly one event (`sole()` throws on none and on more), PII-free, referencing
    // the Variant by id.
    $event = DomainEvent::query()->where('name', 'EnrichmentDataUpdated')->sole();
    expect($event->entity_type)->toBe('ProductVariant')
        ->and($event->entity_id)->toBe((string) $variant->id)
        ->and($event->payload)->toEqual(['product_variant_id' => $variant->id]);

    // One audit row under the operator envelope, carrying the changed field's before/after i18n maps.
    $audit = AuditRecord::query()->where('action', 'catalog.product_variant.enrichment_updated')->sole();
    expect($audit->module)->toBe('catalog')
        ->and($audit->entity_type)->toBe('ProductVariant')
        ->and($audit->entity_id)->toBe((string) $variant->id)
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($audit->actor_id)->toEqual($operator->id)
        // `toEqual`, never `toBe`: a decoded jsonb snapshot map carries the ENGINE's key order.
        ->and($audit->before)->toEqual(['tasting_notes' => ['en' => 'Graphite and cassis.']])
        ->and($audit->after)->toEqual(['tasting_notes' => ['en' => 'Cassis, graphite and violets.']]);
});

it('clears the tasting notes through the console when the textarea is emptied', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $variant = maintenanceConsoleVariant();

    // REPLACEMENT semantics: the modal submits the field on every call, so an emptied textarea CLEARS the prose.
    // What is asserted is the OBSERVABLE contract — the stored value ends up `null`, never a `TranslatableText`
    // holding an empty English string. (The `''` submitted here never reaches the page's own null-coalescing arm:
    // Filament dehydrates a blank Textarea to `null` first. That arm is defence-in-depth and this test does not
    // claim to exercise it — deleting it leaves every assertion below green.)
    Livewire::test(ViewProductVariant::class, ['record' => $variant->getKey()])
        ->callAction('editEnrichment', ['tasting_notes' => ''])
        ->assertHasNoActionErrors();

    $audit = AuditRecord::query()->where('action', 'catalog.product_variant.enrichment_updated')->sole();
    expect(ProductVariant::findOrFail($variant->id)->wineAttributes?->tasting_notes)->toBeNull()
        // A clear is a real change, so it fires the event and audits `null` on the after side (not `['en' => '']`).
        ->and(DomainEvent::query()->where('name', 'EnrichmentDataUpdated')->count())->toBe(1)
        ->and($audit->after)->toEqual(['tasting_notes' => null])
        ->and(ProductVariant::findOrFail($variant->id)->version)->toBe(1);
});

it('treats an unchanged tasting-notes submission as a silent no-op that still reports success', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $variant = maintenanceConsoleVariant();

    // The idempotence contract (design D11 / AC-0-EVT-8) reaches the console unchanged: nothing is written, yet
    // the operator's request DID succeed — the notes read as they asked — so the kit still notifies success.
    Livewire::test(ViewProductVariant::class, ['record' => $variant->getKey()])
        ->callAction('editEnrichment', ['tasting_notes' => 'Graphite and cassis.'])
        ->assertHasNoActionErrors()
        ->assertNotified((string) __('operator_console.product_variant.notifications.enrichment_updated'));

    expect(DomainEvent::query()->where('name', 'EnrichmentDataUpdated')->count())->toBe(0)
        ->and(AuditRecord::query()->where('action', 'catalog.product_variant.enrichment_updated')->count())->toBe(0)
        ->and(ProductVariant::findOrFail($variant->id)->version)->toBe(1);
});

it('surfaces the retired-state guard on the enrichment modal, leaving the retired Variant untouched', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $variant = maintenanceConsoleVariant();
    app(RetireProductVariant::class)->handle($variant);
    expect(ProductVariant::findOrFail($variant->id)->lifecycle_state)->toBe(LifecycleState::Retired);

    // The action stays VISIBLE on a retired Variant — the console hides no write behind a state pre-check
    // (design L4: surface, don't reimplement). The domain rejects, and the kit lands the rejection on the
    // modal's designated field, exactly as the Master's identity modal does.
    Livewire::test(ViewProductVariant::class, ['record' => $variant->getKey()])
        ->assertActionVisible('editEnrichment')
        ->callAction('editEnrichment', ['tasting_notes' => 'Rewritten while retired.'])
        ->assertHasActionErrors(['tasting_notes' => (string) __('catalog.edit.cannot_edit', [
            'entity' => 'ProductVariant',
            'state' => LifecycleState::Retired->value,
        ])]);

    expect(ProductVariant::findOrFail($variant->id)->wineAttributes?->tasting_notes?->resolve('en'))->toBe('Graphite and cassis.')
        ->and(DomainEvent::query()->where('name', 'EnrichmentDataUpdated')->count())->toBe(0)
        ->and(AuditRecord::query()->where('action', 'catalog.product_variant.enrichment_updated')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| manageWhitelist (delta — The whitelist is reduced on an active Variant through the console)
|--------------------------------------------------------------------------
*/

it('re-prefills the admitted set from the pair when the whitelist modal\'s Format changes', function () {
    actingAs(Operator::factory()->create(), 'operator');

    [$variant, $format, , $owc6, $carton12] = maintenanceConsoleWhitelistFixture();
    $otherFormat = Format::factory()->create(['lifecycle_state' => LifecycleState::Active]);

    maintenanceConsoleAdmit($variant, $format, $owc6);
    maintenanceConsoleAdmit($variant, $format, $carton12);
    maintenanceConsoleAdmit($variant, $otherFormat, $carton12);

    $page = Livewire::test(ViewProductVariant::class, ['record' => $variant->getKey()])
        ->mountAction('manageWhitelist')
        // No pair is chosen on open, so nothing is admitted to show.
        ->assertActionDataSet(['format_id' => null, 'case_configuration_ids' => []]);

    // Choosing a Format is what selects the pair. `set()` on the mounted action's state path drives Filament's
    // `live()` hook — the same round-trip the operator's select makes.
    $page->set('mountedActions.0.data.format_id', $format->id)
        ->assertActionDataSet(['case_configuration_ids' => [$owc6->id, $carton12->id]]);

    // Switching Format REPLACES the shown set with the new pair's — the second pair is independent. Without this,
    // an operator who switched Format would submit the first pair's set and silently rewrite the second.
    $page->set('mountedActions.0.data.format_id', $otherFormat->id)
        ->assertActionDataSet(['case_configuration_ids' => [$carton12->id]]);
});

it('replaces a pair\'s admitted set on an active Variant through the console, auditing before/after with no event and no version change', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    [$variant, $format, , $owc6, $carton12] = maintenanceConsoleWhitelistFixture();
    maintenanceConsoleAdmit($variant, $format, $owc6);

    $eventsBefore = DomainEvent::query()->count();

    // The J-13 reduction, plus an addition, in one replace: OWC6 out, CARTON12 in.
    $page = Livewire::test(ViewProductVariant::class, ['record' => $variant->getKey()]);

    $page->callAction('manageWhitelist', ['format_id' => $format->id, 'case_configuration_ids' => [$carton12->id]])
        ->assertHasNoActionErrors()
        ->assertNotified((string) __('operator_console.product_variant.notifications.whitelist_updated'));

    // The pivot holds exactly the replacement set for this pair.
    expect(VariantCaseWhitelistEntry::query()->where('product_variant_id', $variant->id)->where('format_id', $format->id)->pluck('case_configuration_id')->all())
        ->toEqualCanonicalizing([$carton12->id]);

    // Audit-only: the pair travels on both snapshots, `version` stands, and the event log is untouched.
    $audit = AuditRecord::query()->where('action', 'catalog.product_variant.whitelist_updated')->sole();
    expect($audit->entity_type)->toBe('ProductVariant')
        ->and($audit->entity_id)->toBe((string) $variant->id)
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($audit->actor_id)->toEqual($operator->id)
        ->and($audit->before)->toEqual(['format_id' => $format->id, 'case_configurations' => [$owc6->id]])
        ->and($audit->after)->toEqual(['format_id' => $format->id, 'case_configurations' => [$carton12->id]])
        ->and(ProductVariant::findOrFail($variant->id)->version)->toBe(1)
        ->and(maintenanceConsolePageRecord($page)->version)->toBe(1)
        ->and(DomainEvent::query()->count())->toBe($eventsBefore);
});

it('clears a pair through the console when the admitted set is emptied, restoring the permissive default', function () {
    actingAs(Operator::factory()->create(), 'operator');

    [$variant, $format, , $owc6] = maintenanceConsoleWhitelistFixture();
    maintenanceConsoleAdmit($variant, $format, $owc6);

    // An EMPTY set is a legitimate call, which is why the multi-select is not `->required()`: it clears the pair
    // and restores § 7.1's permissive default (absence admits, presence narrows).
    Livewire::test(ViewProductVariant::class, ['record' => $variant->getKey()])
        ->callAction('manageWhitelist', ['format_id' => $format->id, 'case_configuration_ids' => []])
        ->assertHasNoActionErrors()
        ->assertNotified((string) __('operator_console.product_variant.notifications.whitelist_updated'));

    $audit = AuditRecord::query()->where('action', 'catalog.product_variant.whitelist_updated')->sole();
    expect(VariantCaseWhitelistEntry::query()->where('product_variant_id', $variant->id)->count())->toBe(0)
        ->and($audit->after)->toEqual(['format_id' => $format->id, 'case_configurations' => []]);
});

it('surfaces the retired-state guard on the whitelist modal, leaving the pair untouched', function () {
    actingAs(Operator::factory()->create(), 'operator');

    [$variant, $format, , $owc6, $carton12] = maintenanceConsoleWhitelistFixture();
    maintenanceConsoleAdmit($variant, $format, $owc6);
    app(RetireProductVariant::class)->handle($variant);

    Livewire::test(ViewProductVariant::class, ['record' => $variant->getKey()])
        ->assertActionVisible('manageWhitelist')
        ->callAction('manageWhitelist', ['format_id' => $format->id, 'case_configuration_ids' => [$carton12->id]])
        ->assertHasActionErrors(['case_configuration_ids' => (string) __('catalog.edit.cannot_edit', [
            'entity' => 'ProductVariant',
            'state' => LifecycleState::Retired->value,
        ])]);

    expect(VariantCaseWhitelistEntry::query()->where('product_variant_id', $variant->id)->pluck('case_configuration_id')->all())
        ->toEqualCanonicalizing([$owc6->id])
        ->and(AuditRecord::query()->where('action', 'catalog.product_variant.whitelist_updated')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| The gate the console does NOT reimplement (delta — the second half of the whitelist scenario)
|--------------------------------------------------------------------------
|
| "…a subsequent console attempt to activate a new Sellable SKU referencing the removed Case Configuration for
| that pair is rejected by the domain and surfaced as a notification naming the whitelist condition."
|
| Both halves travel through the console: the reduction on ViewProductVariant, the blocked activation on
| ViewSellableSku. The SKU console holds no whitelist code whatsoever — its `activate` is the same uniform kit
| action it always was, and `ActivateSellableSku` is where the gate lives (task 3.2). What proves the surfacing is
| the notification's BODY: the domain's own `catalog.gate.case_configuration_not_whitelisted` copy, verbatim.
*/

it('surfaces the domain whitelist gate when a SKU on a de-admitted Case Configuration is activated through the console', function () {
    actingAs(Operator::factory()->create(), 'operator');

    [$variant, $format, $reference, $owc6, $carton12] = maintenanceConsoleWhitelistFixture();
    maintenanceConsoleAdmit($variant, $format, $owc6);

    // A `reviewed` SKU on OWC6 with a distinct creator and reviewer, so only a GATE can reject its activation.
    actingAs(Operator::factory()->create(), 'operator');
    $sku = app(CreateSellableSku::class)->handle(
        productReferenceId: $reference->id,
        caseConfigurationId: $owc6->id,
        commercialName: 'Château Console 2019 — OWC6',
    );

    actingAs(Operator::factory()->create(), 'operator');
    app(SubmitSellableSkuForReview::class)->handle($sku);

    // The operator de-admits OWC6 for the pair, THROUGH THE CONSOLE — the J-13 reduction. It must be a REDUCTION
    // to CARTON12, never an emptying: an empty pair is § 7.1's PERMISSIVE default and would ADMIT the activation
    // below. The whitelist is Module 0's one gate whose empty read means PASS.
    actingAs(Operator::factory()->create(), 'operator');
    Livewire::test(ViewProductVariant::class, ['record' => $variant->getKey()])
        ->callAction('manageWhitelist', ['format_id' => $format->id, 'case_configuration_ids' => [$carton12->id]])
        ->assertHasNoActionErrors();

    // A distinct approver now tries to activate the SKU through ITS console. Everything else is in order — both
    // cascade parents are `active`, the SoD triple is satisfied — so the whitelist is the only thing that can speak.
    actingAs(Operator::factory()->create(), 'operator');
    Livewire::test(ViewSellableSku::class, ['record' => $sku->getKey()])
        ->callAction('activate');

    // Snapshot the notifications BEFORE any Filament assertion pulls them from the session. The danger TITLE is the
    // SKU console's shared `action_failed`; its BODY is the domain's own localized whitelist message, verbatim —
    // that body is the whole content of "surfaced as a notification naming the whitelist condition", and it is what
    // proves the SKU console reimplements no gate (it holds no whitelist code at all).
    expect(maintenanceConsoleNotifications())->toHaveKey(
        (string) __('operator_console.sellable_sku.notifications.action_failed'),
        (string) __('catalog.gate.case_configuration_not_whitelisted', ['entity' => 'SellableSku']),
    );

    // Rejected inside the transaction: the SKU stands in `reviewed`, and no activation event was recorded.
    expect(SellableSku::findOrFail($sku->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'SellableSKUActivated')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| i18n (R5 — every new CONSOLE key is authored EN + IT in the task that ships its surface; invariant 12)
|--------------------------------------------------------------------------
*/

it('localizes the two maintenance surfaces\' labels, notifications and fields in EN and IT', function (string $key) {
    app()->setLocale('en');
    $en = (string) __($key);
    app()->setLocale('it');
    $it = (string) __($key);

    // Both locales resolve the key (not the raw key) and the IT copy is a genuine translation.
    expect($en)->not->toBe($key)
        ->and($it)->not->toBe($key)
        ->and($it)->not->toBe($en);
})->with([
    'operator_console.product_variant.actions.edit_enrichment',
    'operator_console.product_variant.actions.manage_whitelist',
    'operator_console.product_variant.notifications.enrichment_updated',
    'operator_console.product_variant.notifications.whitelist_updated',
    'operator_console.product_variant.fields.whitelist_format',
    'operator_console.product_variant.fields.whitelist_case_configurations',
    'operator_console.product_variant.fields.whitelist_case_configurations_help',
]);
