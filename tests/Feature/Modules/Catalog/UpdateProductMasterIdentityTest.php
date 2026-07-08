<?php

use App\Modules\Catalog\Actions\ActivateProductMaster;
use App\Modules\Catalog\Actions\CreateProductMaster;
use App\Modules\Catalog\Actions\SubmitProductMasterForReview;
use App\Modules\Catalog\Actions\UpdateProductMasterIdentity;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\DuplicateProductMasterIdentity;
use App\Modules\Catalog\Exceptions\IllegalContentEdit;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductMasterWineAttributes;
use App\Modules\Module;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Audit\AuditRecord;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use App\Platform\Events\DomainEventRecorder;
use App\Platform\I18n\TranslatableText;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

/**
 * Pins `UpdateProductMasterIdentity` — the first real Action on the shared `CatalogContentEdit` mechanism
 * (catalog-module-0-completeness-sweep task 2.1; design D1/D2; product-catalog — Requirement: Identity Edit and
 * Re-Versioning; Module 0 PRD § 4.8 + § 13.3 BR-Audit-1, § 13.1 BR-Identity-1). This is the AC-0-BR-Audit-1
 * Master half, driven end to end: an identity edit creates a new version, the old version stays retrievable
 * from the append-only audit trail (deprecated, never deleted), and the full before/after of the changed fields
 * is recorded.
 *
 * The mechanism's own five mechanics (txn + locked re-read, state guard, operator floor, single-UPDATE version
 * increment, one audit row / no event) are pinned by `CatalogContentEditTest` against a bare closure. What is
 * proven HERE is everything the Action contributes on top: the four-field replacement diff, the BR-Identity-1
 * re-check excluding self, the skipped dedup query on a region/story-only edit, the wine attribute set written
 * inside the mechanism's transaction, and the D4 draft-clear leg through the REAL lifecycle Actions.
 *
 * DatabaseMigrations (mirroring `CatalogContentEditTest` / `ProductMasterLifecycleTest`): the mechanism and the
 * lifecycle Actions each open their OWN top-level `DB::transaction`, so the audit recorder's
 * `transactionLevel() === 0` guard sees a real commit and the inline `ProducerLifecycleProjector` fires on the
 * post-commit hook — both of which `RefreshDatabase`'s wrapping transaction would suppress. Fixtures come from
 * the spine FACTORY where the creation lineage is irrelevant (it bypasses the Actions and so records neither
 * audit rows nor domain events, making every count below attributable to the edit alone), and from the real
 * `CreateProductMaster` where the audit lineage IS the subject.
 */
uses(DatabaseMigrations::class);

/** The locked Master's wine attribute set — the 1:1 per-type row the identity edit writes through. */
function identityWineAttributesOf(ProductMaster $master): ProductMasterWineAttributes
{
    return ProductMasterWineAttributes::query()->where('product_master_id', $master->id)->sole();
}

/**
 * Project a Producer `active` in Catalog's own read model (the Master activation gate's source) by recording a
 * Module-K `ProducerActivated` inside a real transaction, so the inline projector upserts `catalog_producer_states`.
 * Distinctly named for Pest's one shared top-level function namespace.
 */
function identityEditProjectProducerActive(int $producerId): void
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
 * Run $work while capturing every executed SQL statement, and report whether any of them ran the BR-Identity-1
 * dedup join — recognised by its `wine` table ALIAS, which no other query in this module emits (the Action's own
 * `wineAttributes()` read touches the same table unaliased). Both engines' grammars wrap an alias in double
 * quotes, so the needle is engine-neutral.
 */
function identityEditRanDedupQuery(Closure $work): bool
{
    $ran = false;

    DB::listen(function (QueryExecuted $query) use (&$ran): void {
        if (str_contains($query->sql, 'as "wine"')) {
            $ran = true;
        }
    });

    $work();

    return $ran;
}

/**
 * The identity edit's twin of the enrichment regression. An identity edit ALWAYS versions and always audits, so
 * a swallowed story change is quieter here than in the enrichment path — but it is worse: `version` reaches 2,
 * the audit row claims an `identity_updated`, review is re-armed, and the story the operator typed was never
 * written. The old `!=` on the i18n-keyed maps compared two numeric strings NUMERICALLY, so `'1e2'` → `'100'`
 * left `$wineAttributes` empty and the `winery_story` column untouched.
 */
it('writes a winery-story change between two numeric-string texts, which loose comparison would swallow', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $master = ProductMaster::factory()->create(['name' => 'Château Ancien']);
    identityWineAttributesOf($master)->update([
        'appellation' => 'Margaux',
        'region' => 'Bordeaux',
        'winery_story' => TranslatableText::of(['en' => '1e2']),
    ]);

    app(UpdateProductMasterIdentity::class)->handle(
        master: $master,
        name: 'Château Ancien',      // identity key unmoved: the story is the ONLY change
        appellation: 'Margaux',
        region: 'Bordeaux',
        wineryStory: TranslatableText::of(['en' => '100']),
    );

    $audit = AuditRecord::query()->sole();

    expect(identityWineAttributesOf($master)->winery_story?->jsonSerialize())->toBe(['en' => '100'])
        ->and($audit->before)->toEqual(['winery_story' => ['en' => '1e2'], 'version' => 1])
        ->and($audit->after)->toEqual(['winery_story' => ['en' => '100'], 'version' => 2]);
});

/*
|--------------------------------------------------------------------------
| AC-0-BR-Audit-1 (Master half) — the new version, the old version, the before/after
|--------------------------------------------------------------------------
*/

it('re-versions an active Master identity edit in place, auditing before and after and recording no domain event', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $master = ProductMaster::factory()->create([
        'name' => 'Château Ancien',
        'lifecycle_state' => LifecycleState::Active,
    ]);
    identityWineAttributesOf($master)->update([
        'appellation' => 'Margaux',
        'region' => 'Bordeaux',
        'winery_story' => TranslatableText::of(['en' => 'Founded in 1855.']),
    ]);

    $edited = app(UpdateProductMasterIdentity::class)->handle(
        master: $master,
        name: 'Château Nouveau',
        appellation: 'Margaux',
        region: 'Bordeaux',
        wineryStory: TranslatableText::of(['en' => 'Founded in 1855.']),
    );

    // A NEW VERSION, in place: the same row, the same primary key (so every downstream reference survives), the
    // new content, and `version` incremented by EXACTLY one (design D1 — no separate version rows).
    $persisted = ProductMaster::findOrFail($master->id);

    expect($edited->version)->toBe(2)
        ->and($persisted->id)->toBe($master->id)
        ->and($persisted->version)->toBe(2)
        ->and($persisted->name)->toBe('Château Nouveau')
        // An identity edit is not a lifecycle transition: the FSM has no `active → reviewed` edge, so an `active`
        // Master stays `active` and the version + audit are the control on the operator-correction path.
        ->and($persisted->lifecycle_state)->toBe(LifecycleState::Active);

    // ONE audit row, under the content-edit envelope, carrying the CHANGED field only (design R9 — minimal
    // snapshots; the unchanged appellation/region/story never enter before/after).
    $audit = AuditRecord::query()->sole();

    expect($audit->action)->toBe('catalog.product_master.identity_updated')
        ->and($audit->module)->toBe('catalog')
        ->and($audit->entity_type)->toBe('ProductMaster')
        ->and($audit->entity_id)->toBe((string) $master->id)
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($audit->actor_id)->toEqual($operator->id)
        ->and($audit->authorization_basis)->toBe('catalog-content-edit')
        // toEqual, never toBe: PG's jsonb reorders a snapshot map's KEYS by key length (see progress.md).
        ->and($audit->before)->toEqual(['name' => 'Château Ancien', 'version' => 1])
        ->and($audit->after)->toEqual(['name' => 'Château Nouveau', 'version' => 2]);

    // BR-Audit-1 spelled out: the OLD version is retrievable from the append-only trail even though the row itself
    // has moved on — "old versions are deprecated, never deleted".
    expect($audit->before)->toHaveKey('name', 'Château Ancien');

    // The catalog event surface stays closed at the 21 lifecycle events (+ EnrichmentDataUpdated): an identity
    // edit records NO domain event (design D2).
    expect(DomainEvent::query()->count())->toBe(0);
});

it('writes the wine attribute set inside the same edit, auditing only the fields that actually changed', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $master = ProductMaster::factory()->create(['name' => 'Château Ancien']);
    identityWineAttributesOf($master)->update([
        'appellation' => 'Margaux',
        'region' => 'Bordeaux',
        'winery_story' => TranslatableText::of(['en' => 'Founded in 1855.']),
    ]);

    // Appellation and story move; name and region are re-passed UNCHANGED (the console modal always submits all
    // four). Replacement semantics + a diff — the untouched fields must not surface in the audit snapshots.
    app(UpdateProductMasterIdentity::class)->handle(
        master: $master,
        name: 'Château Ancien',
        appellation: 'Pauillac',
        region: 'Bordeaux',
        wineryStory: TranslatableText::of(['en' => 'Founded in 1855.', 'it' => 'Fondata nel 1855.']),
    );

    $wine = identityWineAttributesOf($master);

    expect($wine->appellation)->toBe('Pauillac')
        ->and($wine->region)->toBe('Bordeaux')
        ->and($wine->winery_story?->resolve('it'))->toBe('Fondata nel 1855.')
        ->and(ProductMaster::findOrFail($master->id)->version)->toBe(2);

    $audit = AuditRecord::query()->sole();

    expect($audit->before)->toEqual([
        'appellation' => 'Margaux',
        'winery_story' => ['en' => 'Founded in 1855.'],
        'version' => 1,
    ])->and($audit->after)->toEqual([
        'appellation' => 'Pauillac',
        'winery_story' => ['en' => 'Founded in 1855.', 'it' => 'Fondata nel 1855.'],
        'version' => 2,
    ]);
});

it('clears the winery story when the replacement prose is null, recording the null in the audit trail', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $master = ProductMaster::factory()->create(['name' => 'Château Ancien']);
    identityWineAttributesOf($master)->update([
        'appellation' => 'Margaux',
        'region' => 'Bordeaux',
        'winery_story' => TranslatableText::of(['en' => 'Founded in 1855.']),
    ]);

    app(UpdateProductMasterIdentity::class)->handle(
        master: $master,
        name: 'Château Ancien',
        appellation: 'Margaux',
        region: 'Bordeaux',
        wineryStory: null,
    );

    expect(identityWineAttributesOf($master)->winery_story)->toBeNull();

    $audit = AuditRecord::query()->sole();

    expect($audit->before)->toEqual(['winery_story' => ['en' => 'Founded in 1855.'], 'version' => 1])
        ->and($audit->after)->toEqual(['winery_story' => null, 'version' => 2]);
});

/*
|--------------------------------------------------------------------------
| BR-Identity-1 re-checked on edit — excluding self, ignoring retired
|--------------------------------------------------------------------------
*/

it('rejects an identity edit that collides with another non-retired Master, leaving values and version unchanged', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // The incumbent holds `producer 7 + Château Rival + Pauillac`.
    $rival = ProductMaster::factory()->create(['name' => 'Château Rival', 'producer_id' => 7]);
    identityWineAttributesOf($rival)->update(['appellation' => 'Pauillac']);

    $master = ProductMaster::factory()->create(['name' => 'Château Ancien', 'producer_id' => 7]);
    identityWineAttributesOf($master)->update(['appellation' => 'Margaux', 'region' => 'Bordeaux']);

    expect(fn () => app(UpdateProductMasterIdentity::class)->handle(
        master: $master,
        name: 'Château Rival',
        appellation: 'Pauillac',
        region: 'Bordeaux',
        wineryStory: null,
    ))->toThrow(DuplicateProductMasterIdentity::class, 'identity key');

    // The rejection fires inside the mechanism's transaction, so NOTHING moved: not the core row, not the wine
    // attribute set, not the version, not the audit trail, not the event log.
    $persisted = ProductMaster::findOrFail($master->id);
    $wine = identityWineAttributesOf($master);

    expect($persisted->name)->toBe('Château Ancien')
        ->and($persisted->version)->toBe(1)
        ->and($wine->appellation)->toBe('Margaux')
        ->and($wine->winery_story)->not->toBeNull()      // the null-story write never landed either
        ->and(AuditRecord::query()->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('admits an identity edit that collides only with a retired Master, or only with the Master under edit', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A RETIRED holder of the key does not reserve it (the dedup is scoped to non-retired Masters).
    $retired = ProductMaster::factory()->create([
        'name' => 'Château Rival',
        'producer_id' => 7,
        'lifecycle_state' => LifecycleState::Retired,
    ]);
    identityWineAttributesOf($retired)->update(['appellation' => 'Pauillac']);

    $master = ProductMaster::factory()->create(['name' => 'Château Ancien', 'producer_id' => 7]);
    identityWineAttributesOf($master)->update(['appellation' => 'Margaux', 'region' => 'Bordeaux']);

    app(UpdateProductMasterIdentity::class)->handle(
        master: $master,
        name: 'Château Rival',
        appellation: 'Pauillac',
        region: 'Bordeaux',
        wineryStory: null,
    );

    expect(ProductMaster::findOrFail($master->id)->name)->toBe('Château Rival')
        ->and(ProductMaster::findOrFail($master->id)->version)->toBe(2);

    // And a second edit that re-passes the SAME key it now holds must not collide with ITSELF — here because the
    // key did not move, so the dedup is skipped entirely (the Action's `whereKeyNot` is the belt behind that
    // braces; the next test pins the skip directly).
    app(UpdateProductMasterIdentity::class)->handle(
        master: $master->refresh(),
        name: 'Château Rival',
        appellation: 'Pauillac',
        region: 'Médoc',
        wineryStory: null,
    );

    expect(identityWineAttributesOf($master)->region)->toBe('Médoc')
        ->and(ProductMaster::findOrFail($master->id)->version)->toBe(3);
});

it('runs the dedup join only when the identity key moves', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $master = ProductMaster::factory()->create(['name' => 'Château Ancien']);
    identityWineAttributesOf($master)->update(['appellation' => 'Margaux', 'region' => 'Bordeaux']);

    // Region + story only: `producer + name + appellation` is untouched, so no other Master's key is reachable
    // and the join never runs.
    $ranOnContentEdit = identityEditRanDedupQuery(fn () => app(UpdateProductMasterIdentity::class)->handle(
        master: $master,
        name: 'Château Ancien',
        appellation: 'Margaux',
        region: 'Médoc',
        wineryStory: TranslatableText::of(['en' => 'A new story.']),
    ));

    // The lock-step positive: the SAME needle fires the moment the name moves — so the negative above pins a
    // skipped query, not a broken needle.
    $ranOnIdentityEdit = identityEditRanDedupQuery(fn () => app(UpdateProductMasterIdentity::class)->handle(
        master: $master->refresh(),
        name: 'Château Nouveau',
        appellation: 'Margaux',
        region: 'Médoc',
        wineryStory: TranslatableText::of(['en' => 'A new story.']),
    ));

    expect($ranOnContentEdit)->toBeFalse()
        ->and($ranOnIdentityEdit)->toBeTrue()
        ->and(ProductMaster::findOrFail($master->id)->version)->toBe(3);   // both edits still re-versioned
});

/*
|--------------------------------------------------------------------------
| The floors, through the real Action
|--------------------------------------------------------------------------
*/

it('rejects an identity edit on a retired Master, writing nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $master = ProductMaster::factory()->create([
        'name' => 'Château Ancien',
        'lifecycle_state' => LifecycleState::Retired,
    ]);

    expect(fn () => app(UpdateProductMasterIdentity::class)->handle(
        master: $master,
        name: 'Château Nouveau',
        appellation: 'Margaux',
        region: 'Bordeaux',
        wineryStory: null,
    ))->toThrow(IllegalContentEdit::class, 'reopened');

    expect(ProductMaster::findOrFail($master->id)->name)->toBe('Château Ancien')
        ->and(ProductMaster::findOrFail($master->id)->version)->toBe(1)
        ->and(AuditRecord::query()->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('rejects an identity edit under a system actor, writing nothing', function () {
    // No actingAs(): the ActorContext resolves (System, null) — a content edit is an inherently human decision,
    // and the floor is checked BEFORE the Action's dedup re-check ever runs.
    $master = ProductMaster::factory()->create(['name' => 'Château Ancien']);

    expect(fn () => app(UpdateProductMasterIdentity::class)->handle(
        master: $master,
        name: 'Château Nouveau',
        appellation: 'Margaux',
        region: 'Bordeaux',
        wineryStory: null,
    ))->toThrow(ApprovalGovernanceViolation::class);

    expect(ProductMaster::findOrFail($master->id)->name)->toBe('Château Ancien')
        ->and(ProductMaster::findOrFail($master->id)->version)->toBe(1)
        ->and(AuditRecord::query()->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| The D4 draft-clear leg — a draft edit versions the entity without arming review
|--------------------------------------------------------------------------
|
| Delta scenario "A draft edit versions the entity without arming review", now through the REAL Action rather
| than a simulated audit row (task 1.2 proved the derivation; this proves the Action feeds it the right verb).
| `.submitted` is in the review-freshness-relevant set precisely so the submit that FOLLOWS a draft edit becomes
| the latest relevant action — otherwise a draft edit would block its Master's activation forever.
*/

it('does not block activation when a draft identity edit is followed by a submit and a distinct-approver activation', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    identityEditProjectProducerActive(7);

    actingAs($creator, 'operator');
    $master = app(CreateProductMaster::class)->handle(
        name: 'Château Margaux',
        producerId: 7,
        appellation: 'Margaux',
        region: 'Bordeaux',
    );

    // The Creator corrects the identity while the Master is still `draft` — free, but versioned and audited.
    app(UpdateProductMasterIdentity::class)->handle(
        master: $master,
        name: 'Château Margaux Grand Vin',
        appellation: 'Margaux',
        region: 'Bordeaux',
        wineryStory: null,
    );

    expect(ProductMaster::findOrFail($master->id)->version)->toBe(2);

    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master->refresh());

    actingAs($approver, 'operator');
    $active = app(ActivateProductMaster::class)->handle($master->refresh());

    // The `.submitted` row is the latest review-freshness-relevant action, so the earlier `.identity_updated`
    // never blocks: the Master activates, once, under a distinct approver.
    expect($active->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(1)
        ->and(AuditRecord::query()->where('action', 'catalog.product_master.identity_updated')->count())->toBe(1);
});
