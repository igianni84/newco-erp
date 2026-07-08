<?php

// Task 6.1 (catalog-module-0-completeness-sweep; design D8/R8; spec — Operator edits catalog identity content
// through the console + Operator creates a Product Master through the console) — the Product Master's ONE
// field-edit surface: the `editIdentity` modal header action on the view page, and the create page's
// unknown-producer mapping.
//
// The console SURFACES the domain, it reimplements none of it: `UpdateProductMasterIdentity` owns the
// BR-Identity-1 dedup re-check, the `retired` state guard, the operator floor, the `version` increment, the
// audited before/after and the review re-arm. What THIS file pins is the wiring — that the modal is prefilled
// from the record, that a successful edit re-reads the record so the view's `version` entry renders the
// post-edit truth, and that every localized domain rejection lands as a FORM VALIDATION ERROR on the modal's
// `name` field (the spec's requirement for the dedup collision; permitted, and chosen uniformly, for the state
// guard — the console cannot type-discriminate the RuntimeExceptions it catches).
//
// DatabaseMigrations (mirroring ProductMasterLifecycleConsoleTest): the console action drives a real domain
// action that opens its OWN DB::transaction, so the AuditRecorder's transaction-level guard sees a real commit
// (RefreshDatabase would wrap every write in a never-committed outer transaction). Catalog enums/models/actions
// are imported freely here: the {Models, Actions} import-boundary carve-out governs OperatorPanel PRODUCTION
// code, not tests.

use App\Modules\Catalog\Actions\ActivateProductMaster;
use App\Modules\Catalog\Actions\CreateProductMaster;
use App\Modules\Catalog\Actions\RetireProductMaster;
use App\Modules\Catalog\Actions\SubmitProductMasterForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Enums\ProducerProjectionStatus;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages\CreateProductMaster as CreateProductMasterPage;
use App\Modules\OperatorPanel\Filament\Resources\Catalog\ProductMasterResource\Pages\ViewProductMaster;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Audit\AuditRecord;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use App\Platform\I18n\TranslatableText;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\Catalog\ProducerProjectionFixture;

use function Pest\Laravel\actingAs;

uses(DatabaseMigrations::class);

/**
 * A `draft` Master created through the real Catalog action as the currently-acting operator. The producer is
 * seeded `registered` — the weakest status that admits creation (AC-0-XM-2) and still leaves the activation
 * gate closed, so nothing here can activate by accident.
 */
function identityEditDraftMaster(
    int $producerId = 55,
    string $name = 'Château Console',
    string $appellation = 'Pauillac',
    ?string $wineryStory = 'A console-created estate.',
): ProductMaster {
    return app(CreateProductMaster::class)->handle(
        name: $name,
        producerId: ProducerProjectionFixture::known($producerId),
        appellation: $appellation,
        region: 'Bordeaux',
        wineryStory: $wineryStory === null ? null : TranslatableText::of(['en' => $wineryStory]),
    );
}

/**
 * Drive a fresh Master to `active` through the real domain actions with a distinct creator → reviewer →
 * approver lineage (the production-default role_count 3) over an `active` producer projection — the shape the
 * BR-Audit-1 AUTO scenario edits. Returns the active Master.
 */
function identityEditActiveMaster(Operator $creator, Operator $reviewer, Operator $approver): ProductMaster
{
    ProducerProjectionFixture::known(7, ProducerProjectionStatus::Active);

    actingAs($creator, 'operator');
    $master = identityEditDraftMaster(producerId: 7);

    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);

    actingAs($approver, 'operator');
    app(ActivateProductMaster::class)->handle($master);

    return $master;
}

/**
 * The record instance the PAGE holds after its last request — the very object the infolist renders, not a
 * re-query of the row. Narrowed by an `instanceof` guard (the production `recordOf()` idiom) rather than a cast,
 * so the assertion that reads `version` off it is typed all the way down.
 *
 * @param  Testable<ViewProductMaster>  $page
 */
function identityEditPageRecord(Testable $page): ProductMaster
{
    $record = $page->instance()->getRecord();

    if (! $record instanceof ProductMaster) {
        throw new LogicException('The Livewire page under test does not hold a Product Master.');
    }

    return $record;
}

it('exposes an edit-identity action prefilled with the Master\'s current identity content', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $master = identityEditDraftMaster();

    // The modal opens on the CURRENT identity — the three stored fields plus `country`, which is not stored at
    // all but reverse-derived from the region so the Region cascade opens on the right country's options.
    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->mountAction('editIdentity')
        ->assertActionDataSet([
            'name' => 'Château Console',
            'country' => 'France',
            'region' => 'Bordeaux',
            'appellation' => 'Pauillac',
            'winery_story' => 'A console-created estate.',
        ]);
});

it('edits an active Master\'s identity through the console, incrementing version and recording one audit row with no domain event', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $master = identityEditActiveMaster($creator, $reviewer, $approver);
    expect(ProductMaster::findOrFail($master->id)->version)->toBe(1);

    $eventsBeforeEdit = DomainEvent::query()->count();

    // The BR-Audit-1 AUTO scenario through the console: an `active` Master's product name is corrected. Any
    // authenticated operator may edit (the edit path carries the operator floor, not the distinct-actor SoD).
    actingAs($approver, 'operator');
    $page = Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()]);

    $page->callAction('editIdentity', ['name' => 'Château Console Corrigé', 'appellation' => 'Margaux'])
        ->assertHasNoActionErrors()
        ->assertNotified((string) __('operator_console.product_master.notifications.identity_updated'))
        // The infolist re-rendered on the edited content — not on the values the page mounted with.
        ->assertSee('Château Console Corrigé')
        ->assertSee('Margaux');

    // In-place re-versioning: version 1 → 2, and the Master stays `active` (the FSM has no active → reviewed edge).
    $edited = ProductMaster::findOrFail($master->id);
    expect($edited->name)->toBe('Château Console Corrigé')
        ->and($edited->version)->toBe(2)
        ->and($edited->lifecycle_state)->toBe(LifecycleState::Active);

    // The VIEW reflects the increment (spec: "the View SHALL reflect the incremented `version`"). Read off the
    // PAGE's record, not the DB: the Action mutates the very instance the page handed it, so the `version`
    // infolist entry renders 2 in this response. An Action that wrote a COPY would leave this at 1.
    expect(identityEditPageRecord($page)->version)->toBe(2);

    // Exactly one identity-edit audit row carrying the operator envelope; the old name is retrievable from it.
    $audit = AuditRecord::query()->where('action', 'catalog.product_master.identity_updated')->sole();
    $before = $audit->before ?? [];
    $after = $audit->after ?? [];

    expect($audit->module)->toBe('catalog')
        ->and($audit->entity_type)->toBe('ProductMaster')
        ->and($audit->entity_id)->toBe((string) $master->id)
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($audit->actor_id)->toEqual($approver->id)
        // Only the CHANGED fields are snapshot (design R9); the untouched region/winery-story are absent.
        ->and($before['name'] ?? null)->toBe('Château Console')
        ->and($before['appellation'] ?? null)->toBe('Pauillac')
        ->and($before['version'] ?? null)->toBe(1)
        ->and($after['name'] ?? null)->toBe('Château Console Corrigé')
        ->and($after['appellation'] ?? null)->toBe('Margaux')
        ->and($after['version'] ?? null)->toBe(2)
        ->and(array_keys($before))->toEqualCanonicalizing(['name', 'appellation', 'version'])
        ->and(array_keys($after))->toEqualCanonicalizing(['name', 'appellation', 'version']);

    // Event-silent: an identity edit records NO domain event (design D2 — the event surface is unchanged).
    expect(DomainEvent::query()->count())->toBe($eventsBeforeEdit);
});

it('surfaces a BR-Identity-1 dedup collision as a form validation error on the modal, changing nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // Two non-retired Masters under the SAME producer; editing the second into the first's identity key
    // (producer + name + appellation) collides.
    identityEditDraftMaster(name: 'Château Dup', appellation: 'Margaux');
    $master = identityEditDraftMaster(name: 'Château Console', appellation: 'Pauillac');

    $eventsBeforeEdit = DomainEvent::query()->count();

    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->callAction('editIdentity', ['name' => 'Château Dup', 'appellation' => 'Margaux'])
        // The DOMAIN's own localized message, on the modal's `name` field — not a 500, and not a notification.
        ->assertHasActionErrors(['name' => (string) __('catalog.product_master.duplicate_identity', [
            'name' => 'Château Dup',
            'appellation' => 'Margaux',
            'producer' => 55,
        ])]);

    // Unchanged: the collision was raised inside the mechanism's transaction, before any write.
    $unchanged = ProductMaster::findOrFail($master->id);
    expect($unchanged->name)->toBe('Château Console')
        ->and($unchanged->version)->toBe(1)
        ->and($unchanged->wineAttributes?->appellation)->toBe('Pauillac')
        ->and(AuditRecord::query()->where('action', 'catalog.product_master.identity_updated')->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe($eventsBeforeEdit);
});

it('surfaces the retired-state guard on the identity-edit modal, leaving the retired Master untouched', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $master = identityEditActiveMaster($creator, $reviewer, $approver);
    app(RetireProductMaster::class)->handle($master);
    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Retired);

    // The action stays VISIBLE on a retired Master — the console hides no write behind a state pre-check
    // (design L4: surface, don't reimplement; only re-submit is visibility-gated, off a DERIVED read). The
    // domain rejects, and the rejection lands on the modal's field exactly as the dedup collision does.
    Livewire::test(ViewProductMaster::class, ['record' => $master->getKey()])
        ->assertActionVisible('editIdentity')
        ->callAction('editIdentity', ['name' => 'Château Retired Rename'])
        // `entity` is the mechanism's entity-type label (`ProductMaster`), the same token the audit action string
        // carries — not a display name. The message is the DOMAIN's, rendered verbatim under the field.
        ->assertHasActionErrors(['name' => (string) __('catalog.edit.cannot_edit', [
            'entity' => 'ProductMaster',
            'state' => LifecycleState::Retired->value,
        ])]);

    $unchanged = ProductMaster::findOrFail($master->id);
    expect($unchanged->name)->toBe('Château Console')
        ->and($unchanged->version)->toBe(1)
        ->and($unchanged->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(AuditRecord::query()->where('action', 'catalog.product_master.identity_updated')->count())->toBe(0);
});

it('localizes the identity-edit label and success notification in EN and IT', function (string $key) {
    app()->setLocale('en');
    $en = (string) __($key);
    app()->setLocale('it');
    $it = (string) __($key);

    // Both locales resolve the key (not the raw key) and the IT copy is a genuine translation (R5: every new
    // CONSOLE key is authored EN + IT in the task that ships its surface; invariant 12).
    expect($en)->not->toBe($key)
        ->and($it)->not->toBe($key)
        ->and($it)->not->toBe($en);
})->with([
    'operator_console.product_master.actions.edit_identity',
    'operator_console.product_master.notifications.identity_updated',
]);

/*
|--------------------------------------------------------------------------
| The create page's unknown-producer mapping (delta — Operator creates a Product Master through the console)
|--------------------------------------------------------------------------
|
| The requirement states the layering exactly: "the create form's producer selector is populated from the
| projection (which, with the widened projection, lists `registered`, `active` and `retired` producers), and the
| domain existence guard is the backstop behind it." Both halves are asserted below. A stale form value never
| reaches the guard through this surface — the Select's own in-rule, evaluated against the SAME projection the
| guard reads, rejects it first — which is precisely what makes the guard a BACKSTOP rather than the front line.
| Its own proof lives at the domain level (ProducerExistenceGuardTest, task 5.2); that it would surface as a form
| error if reached is proven by the dedup collision, which travels the identical RuntimeException → form-error
| path in OperatorConsoleCreateRecord::handleRecordCreation().
*/

it('surfaces a producer unknown to the projection as a create-form validation error, creating nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // Producer 4242 exists; 9999 has no projection row at all (the stale-form-value case).
    ProducerProjectionFixture::known(4242, ProducerProjectionStatus::Active);

    Livewire::test(CreateProductMasterPage::class)
        ->fillForm([
            'name' => 'Château Phantom',
            'producer_id' => 9999,
            'appellation' => 'Pauillac',
            'region' => 'Bordeaux',
        ])
        ->call('create')
        ->assertHasFormErrors(['producer_id']);

    expect(ProductMaster::query()->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'ProductMasterCreated')->count())->toBe(0);
});

it('offers a merely registered producer on the create form — creatable is not activatable (D8)', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A `registered` producer: it EXISTS in the projection, so it is a valid create-form option (the Select's
    // in-rule reads the same projection) AND the domain existence guard admits it. The activation gate still
    // demands `active`, so the Master lands — and stays — in `draft`.
    ProducerProjectionFixture::known(4243, ProducerProjectionStatus::Registered);

    Livewire::test(CreateProductMasterPage::class)
        ->fillForm([
            'name' => 'Château Registered',
            'producer_id' => 4243,
            'appellation' => 'Pauillac',
            'region' => 'Bordeaux',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $master = ProductMaster::query()->where('name', 'Château Registered')->sole();

    expect($master->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and($master->producer_id)->toBe(4243)
        ->and(DomainEvent::query()->where('name', 'ProductMasterCreated')->count())->toBe(1);
});
