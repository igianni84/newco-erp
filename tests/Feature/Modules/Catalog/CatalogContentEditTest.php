<?php

use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalContentEdit;
use App\Modules\Catalog\Lifecycle\CatalogContentEdit;
use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Audit\AuditRecord;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

/**
 * Pins the shared CONTENT-EDIT mechanism (catalog-module-0-completeness-sweep task 1.3; design D1/D2/D3;
 * product-catalog — Requirement: In-Place Versioned Identity Edits; Module 0 PRD BR-Audit-1 / DEC-073).
 *
 * The mechanism is the sibling of `LifecycleTransition`, not part of it: an edit changes CONTENT, never
 * `lifecycle_state`. These tests prove its five shared mechanics independent of any per-entity Action (those
 * land in tasks 2.x/3.x/4.x and supply only their `$apply` closure): the transaction + `lockForUpdate` re-read;
 * the `draft`/`reviewed`/`active` state guard with `retired` rejected; the operator-principal floor; the
 * in-place `version` increment applied in the SAME `UPDATE` as the field writes; and the ONE
 * `catalog.<segment>.<verb>` audit row carrying before/after of the changed fields plus the version on both
 * sides — with NO domain event.
 *
 * `$apply` is exercised with a bare closure standing in for a future Action, so this file tests the MECHANISM
 * and nothing else. The two rejection paths additionally assert the closure is never invoked: a rejected edit
 * must run none of the Action's re-checks and write nothing.
 *
 * DatabaseMigrations (mirroring `ProductMasterLifecycleTest`): the mechanism opens its OWN `DB::transaction`,
 * so the audit recorder's `transactionLevel() === 0` guard sees a REAL commit (level 0 → 1 → 0) — the faithful
 * production shape. Fixtures come from the spine FACTORIES, which bypass the creation Actions and so record
 * neither audit rows nor domain events: every `AuditRecord`/`DomainEvent` count below is therefore attributable
 * to the edit alone.
 */
uses(DatabaseMigrations::class);

/** The `$apply` contract: the entity's own changed columns plus the audit before/after of the changed fields. */
function contentEditRename(string $from, string $to): Closure
{
    return fn (Model $model): array => [
        'attributes' => ['name' => $to],
        'before' => ['name' => $from],
        'after' => ['name' => $to],
    ];
}

it('applies an in-place re-versioning edit, recording one audit row and no domain event', function () {
    $operator = Operator::factory()->create();
    actingAs($operator, 'operator');

    $master = ProductMaster::factory()->create(['name' => 'Château Ancien']);

    $edited = app(CatalogContentEdit::class)->edit(
        $master,
        'ProductMaster',
        'identity_updated',
        contentEditRename('Château Ancien', 'Château Nouveau'),
    );

    // In place: same row, new content, version incremented by exactly one — on the returned model AND on the
    // persisted row (the field write and the increment travel in one UPDATE).
    $persisted = ProductMaster::findOrFail($master->id);

    expect($edited->version)->toBe(2)
        ->and($edited->name)->toBe('Château Nouveau')
        ->and($persisted->id)->toBe($master->id)
        ->and($persisted->version)->toBe(2)
        ->and($persisted->name)->toBe('Château Nouveau')
        ->and($persisted->lifecycle_state)->toBe(LifecycleState::Draft);  // an edit is not a transition

    // Exactly ONE audit row, carrying the content-edit envelope and the before/after of the changed field.
    $audit = AuditRecord::query()->sole();

    expect($audit->action)->toBe('catalog.product_master.identity_updated')
        ->and($audit->module)->toBe('catalog')
        ->and($audit->entity_type)->toBe('ProductMaster')
        ->and($audit->entity_id)->toBe((string) $master->id)          // envelope entity_id is a string
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)         // resolved from the ActorContext seam
        ->and($audit->actor_id)->toEqual($operator->id)             // uncast bigint; loose compare spans engines
        ->and($audit->authorization_basis)->toBe('catalog-content-edit')
        // toEqual, never toBe: PostgreSQL's jsonb does not preserve key insertion ORDER (it sorts keys by
        // length, then bytewise), while SQLite stores the JSON text verbatim — so an identical-comparison of a
        // snapshot MAP is engine-dependent. `==` on arrays ignores key order but still compares nested LISTS
        // element-wise by index, which is the property the ordered-constituent audits (task 2.2) rely on.
        ->and($audit->before)->toEqual(['name' => 'Château Ancien', 'version' => 1])
        ->and($audit->after)->toEqual(['name' => 'Château Nouveau', 'version' => 2]);

    // BR-Audit-1 spelled out: the OLD version stays retrievable from the append-only trail (the row itself has
    // moved on to version 2) — which is why an in-place edit needs no separate version rows (design D1).
    expect($audit->before)->toHaveKey('name', 'Château Ancien');

    // The catalog event surface stays closed: an edit records NO domain event (design D2).
    expect(DomainEvent::query()->count())->toBe(0);
});

it('edits content in every editable state, leaving the lifecycle state untouched', function (LifecycleState $state) {
    actingAs(Operator::factory()->create(), 'operator');

    $master = ProductMaster::factory()->create(['name' => 'Château Ancien', 'lifecycle_state' => $state]);

    app(CatalogContentEdit::class)->edit(
        $master,
        'ProductMaster',
        'identity_updated',
        contentEditRename('Château Ancien', 'Château Nouveau'),
    );

    $persisted = ProductMaster::findOrFail($master->id);

    // `active` content is correctable in place — the FSM has no `active → reviewed` edge, so the state holds
    // and it is the review-freshness derivation (not this guard) that re-arms review.
    expect($persisted->lifecycle_state)->toBe($state)
        ->and($persisted->version)->toBe(2)
        ->and($persisted->name)->toBe('Château Nouveau')
        ->and(AuditRecord::query()->count())->toBe(1);
})->with([
    'draft' => LifecycleState::Draft,
    'reviewed' => LifecycleState::Reviewed,
    'active' => LifecycleState::Active,
]);

it('rejects an edit on a retired entity, writing nothing and never invoking the change closure', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $master = ProductMaster::factory()->create([
        'name' => 'Château Ancien',
        'lifecycle_state' => LifecycleState::Retired,
    ]);

    $applied = false;

    expect(fn () => app(CatalogContentEdit::class)->edit(
        $master,
        'ProductMaster',
        'identity_updated',
        function (Model $model) use (&$applied): array {
            $applied = true;

            return ['attributes' => ['name' => 'Château Nouveau'], 'before' => [], 'after' => []];
        },
    ))->toThrow(IllegalContentEdit::class, 'reopened');

    // The transaction rolled back before any write — and before the Action's own semantics ever ran.
    $persisted = ProductMaster::findOrFail($master->id);

    expect($applied)->toBeFalse()
        ->and($persisted->name)->toBe('Château Ancien')
        ->and($persisted->version)->toBe(1)
        ->and($persisted->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(AuditRecord::query()->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('rejects an edit under a system actor, writing nothing and never invoking the change closure', function () {
    // No actingAs(): the ActorContext resolves (System, null) — a content edit is an inherently human decision.
    $master = ProductMaster::factory()->create(['name' => 'Château Ancien']);

    $applied = false;

    expect(fn () => app(CatalogContentEdit::class)->edit(
        $master,
        'ProductMaster',
        'identity_updated',
        function (Model $model) use (&$applied): array {
            $applied = true;

            return ['attributes' => ['name' => 'Château Nouveau'], 'before' => [], 'after' => []];
        },
    ))->toThrow(ApprovalGovernanceViolation::class);

    $persisted = ProductMaster::findOrFail($master->id);

    expect($applied)->toBeFalse()
        ->and($persisted->name)->toBe('Château Ancien')
        ->and($persisted->version)->toBe(1)
        ->and(AuditRecord::query()->count())->toBe(0)
        ->and(DomainEvent::query()->count())->toBe(0);
});

it('guards the state against the transaction-locked re-read, not the caller\'s stale snapshot', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $master = ProductMaster::factory()->create(['name' => 'Château Ancien']);

    // The row is retired behind the caller's back; the in-memory instance still believes it is `draft`.
    DB::table('catalog_product_masters')
        ->where('id', $master->id)
        ->update(['lifecycle_state' => LifecycleState::Retired->value]);

    expect($master->lifecycle_state)->toBe(LifecycleState::Draft);  // the stale snapshot the caller holds

    expect(fn () => app(CatalogContentEdit::class)->edit(
        $master,
        'ProductMaster',
        'identity_updated',
        contentEditRename('Château Ancien', 'Château Nouveau'),
    ))->toThrow(IllegalContentEdit::class, 'retired');

    // The locked re-read is what the guard asserted on: DB truth, so the edit was refused and wrote nothing.
    $persisted = ProductMaster::findOrFail($master->id);

    expect($persisted->name)->toBe('Château Ancien')
        ->and($persisted->version)->toBe(1)
        ->and(AuditRecord::query()->count())->toBe(0);
});

it('derives the audit action segment from the edited model\'s own table', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A second spine entity — and one whose table pluralises an acronym (`catalog_composite_skus`). The
    // Composite SKU's content lives entirely in its join table, so the edit writes NO own columns: the version
    // increment is then the whole UPDATE, which is exactly the shape task 2.2's composition edit needs.
    $compositeSku = CompositeSku::factory()->create();

    app(CatalogContentEdit::class)->edit(
        $compositeSku,
        'CompositeSku',
        'identity_updated',
        fn (Model $model): array => [
            'attributes' => [],
            'before' => ['constituents' => [1, 2]],
            'after' => ['constituents' => [2, 1]],
        ],
    );

    $audit = AuditRecord::query()->sole();

    // toEqual (see above): the jsonb round-trip reorders the snapshot's KEYS, but the constituent LIST's order
    // — the whole point of an ordered-composition audit — is still compared element-wise by index.
    expect($audit->action)->toBe('catalog.composite_sku.identity_updated')
        ->and($audit->entity_type)->toBe('CompositeSku')
        ->and($audit->before)->toEqual(['constituents' => [1, 2], 'version' => 1])
        ->and($audit->after)->toEqual(['constituents' => [2, 1], 'version' => 2])
        ->and(CompositeSku::findOrFail($compositeSku->id)->version)->toBe(2)
        ->and(DomainEvent::query()->count())->toBe(0);
});
