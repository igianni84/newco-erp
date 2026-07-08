<?php

use App\Modules\Catalog\Actions\ActivateProductMaster;
use App\Modules\Catalog\Actions\CreateProductMaster;
use App\Modules\Catalog\Actions\RejectProductMasterReview;
use App\Modules\Catalog\Actions\ResubmitProductMasterForReview;
use App\Modules\Catalog\Actions\SubmitProductMasterForReview;
use App\Modules\Catalog\Actions\UpdateProductMasterIdentity;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Lifecycle\ApprovalGovernance;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Module;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Audit\AuditRecord;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use App\Platform\Events\DomainEventRecorder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

/**
 * The DEC-019 **re-arm leg**, end to end through the REAL Actions (catalog-module-0-completeness-sweep task 2.3;
 * design D4; product-catalog — Requirement: Approval Governance, scenarios "An identity edit in reviewed re-arms
 * review and blocks activation until re-submit" and "Two rejection rounds each block until re-submit and preserve
 * full history"; Module 0 PRD § 4.3 + § 4.8, AC-0-J-7). This is the leg
 * `decisions/2026-07-02-adopt-dec-019-review-freshness-resubmit.md` deferred to RM-14: an identity edit is the
 * SECOND cause of review-staleness, alongside a rejection.
 *
 * The two halves were built apart and are joined here. Task 1.2 built the verb-filtered derivation
 * ({@see ApprovalGovernance} — among the four review-freshness-relevant verbs the
 * LATEST wins) and pinned it against audit rows written by hand; task 2.1 built the Action
 * ({@see UpdateProductMasterIdentity}) that emits the `identity_updated` verb, and pinned its `draft`-stage leg.
 * Neither proved the two interlock through a `reviewed` Master and the real lifecycle Actions — each was wired to
 * a fixture, not to the other. That is the whole subject of this file: it adds no production code, only the join.
 *
 * What the composed proof adds over its parts:
 *
 *   - **Latest-wins is not any-rejection-pending.** In round 2 of the J-7 flow the trail holds a `.rejected` AND
 *     a later `.identity_updated`; the block that fires names the EDIT, not the rejection. A derivation that
 *     scanned for "is there an un-remediated rejection?" would pass every count assertion below and still fail
 *     this one, because the two causes carry distinct localized reasons (discriminating tokens `un-remediated`
 *     vs `edited`) and the operator reads the reason, not the count.
 *   - **A re-submit clears BOTH causes with one operation.** The remedy does not depend on which cause armed it.
 *   - **The edit never breaks the separation-of-duties lineage.** `reviewerOf` reads the latest `%.submitted`
 *     row; `.identity_updated` does not match it (nor does `.resubmitted` — the char before `submitted` is `e`,
 *     not `.`), so the reviewer stays R across both rounds and A ∉ {C, R} still holds at the final activation.
 *
 * DatabaseMigrations (mirroring `ProductMasterLifecycleTest` / `UpdateProductMasterIdentityTest`): the content-edit
 * mechanism and every lifecycle Action open their OWN top-level `DB::transaction`, so the audit recorder's
 * `transactionLevel() === 0` guard sees a real commit and the inline `ProducerLifecycleProjector` fires on the
 * post-commit hook — both suppressed by `RefreshDatabase`'s wrapping transaction. Fixtures come from the real
 * `CreateProductMaster` throughout: the creation lineage IS the subject (`creatorOf` reads the `*Created` event),
 * so the factory shortcut is unavailable here.
 */
uses(DatabaseMigrations::class);

/**
 * Project a Producer `active` in Catalog's own read model (the Master activation gate's source) by recording a
 * Module-K `ProducerActivated` inside a real transaction, so the inline projector upserts `catalog_producer_states`.
 * The producer gate is held OPEN throughout: what must fire in these tests is the review-freshness block, and a
 * closed producer gate would mask it behind a different exception. Distinctly named for Pest's one shared
 * top-level function namespace.
 */
function rearmProjectProducerActive(int $producerId): void
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

/** The Creator creates the `draft` Master through the real Action — recording the `*Created` event `creatorOf` reads. */
function rearmCreateDraftMaster(Operator $creator, int $producerId = 7): ProductMaster
{
    actingAs($creator, 'operator');

    return app(CreateProductMaster::class)->handle(
        name: 'Château Margaux',
        producerId: $producerId,
        appellation: 'Margaux',
        region: 'Bordeaux',
    );
}

/**
 * The Creator edits the Master's review-governed identity content in place (§ 4.3 — "the Creator edits in place"),
 * moving only the product name. Appellation and region are re-passed unchanged: the Action has replacement
 * semantics (the console modal always submits all four fields) and diffs against the locked row.
 */
function rearmRenameMaster(Operator $creator, ProductMaster $master, string $name): void
{
    actingAs($creator, 'operator');

    app(UpdateProductMasterIdentity::class)->handle(
        master: $master->refresh(),
        name: $name,
        appellation: 'Margaux',
        region: 'Bordeaux',
        wineryStory: null,
    );
}

/**
 * The Master's complete audit trail as an ordered list of action strings — the append-only history, read in
 * append order. Asserting the whole list at once pins order AND per-verb counts AND the absence of any extra row
 * in a single expectation. Hydrating the models (rather than `pluck('action')`) reads `action` through its
 * `@property string` declaration, so no `mixed` cast is needed; `array_values` because PHPStan max types
 * `Collection::map()->all()` as `array<int, string>`, never a `list` (Codebase Patterns).
 *
 * @return list<string>
 */
function rearmAuditActions(ProductMaster $master): array
{
    return array_values(
        AuditRecord::query()
            ->where('module', Module::Catalog->value)
            ->where('entity_type', 'ProductMaster')
            ->where('entity_id', (string) $master->id)
            ->orderBy('id')
            ->get()
            ->map(fn (AuditRecord $record): string => $record->action)
            ->all()
    );
}

/** Assert the Master sits un-activated in `reviewed` — the invariant every blocked activation must leave behind. */
function rearmExpectBlockedInReviewed(ProductMaster $master): void
{
    expect(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(0);
}

/*
|--------------------------------------------------------------------------
| (a) The edit-only re-arm: submit → edit → BLOCKED → re-submit → activate
|--------------------------------------------------------------------------
|
| Delta scenario "An identity edit in reviewed re-arms review and blocks activation until re-submit". No
| rejection anywhere in this history: the entity was submitted, never refused, and is blocked purely because its
| review-governed content moved after the last review decision. An approver looking at it would be approving
| something nobody reviewed.
*/

it('blocks a distinct approver on a reviewed Master edited after submit, until an explicit re-submit re-arms review', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    rearmProjectProducerActive(7);

    $master = rearmCreateDraftMaster($creator);

    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);

    // The Creator corrects the identity while the Master sits in `reviewed`. The FSM has no `active → reviewed`
    // (nor `reviewed → draft`) edge: the Master stays exactly where it was, re-versioned and audited.
    rearmRenameMaster($creator, $master, 'Château Margaux Grand Vin');

    expect(ProductMaster::findOrFail($master->id)->version)->toBe(2)
        ->and(ProductMaster::findOrFail($master->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // A distinct approver — who breaches no separation-of-duties floor — is nonetheless refused: the latest
    // review-freshness-relevant action is the edit, so the entity is not in an activatable review-state AT ALL.
    // The reason names the EDIT (`edited`), never a rejection: there has never been one.
    actingAs($approver, 'operator');
    expect(fn () => app(ActivateProductMaster::class)->handle($master->refresh()))
        ->toThrow(ApprovalGovernanceViolation::class, 'edited');

    rearmExpectBlockedInReviewed($master);

    // The remedy is the explicit `re-submit` — a `reviewed → reviewed`, audit-only Creator decision. It re-arms
    // review by becoming the freshest review-freshness-relevant action.
    actingAs($creator, 'operator');
    app(ResubmitProductMasterForReview::class)->handle($master->refresh());

    actingAs($approver, 'operator');
    $active = app(ActivateProductMaster::class)->handle($master->refresh());

    expect($active->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(1)
        // Activation is not a content edit: the version stands where the edit left it.
        ->and(ProductMaster::findOrFail($master->id)->version)->toBe(2);

    // The append-only trail, whole and in order — the identity edit sits between the submit that armed review and
    // the re-submit that re-armed it. `CreateProductMaster` records no audit row (its history is the `*Created`
    // event), so the trail opens at the submit.
    expect(rearmAuditActions($master))->toBe([
        'catalog.product_master.submitted',
        'catalog.product_master.identity_updated',
        'catalog.product_master.resubmitted',
        'catalog.product_master.activated',
    ]);
});

/*
|--------------------------------------------------------------------------
| (b) AC-0-J-7 with a real edit inside each round
|--------------------------------------------------------------------------
|
| `ProductMasterLifecycleTest` drives the two rejection rounds with no edit between reject and re-submit — the
| rejection alone arms the block, and the re-submit clears it. That is the flow's skeleton. THIS is the flow as
| § 4.3 actually describes it: "the Creator edits the entity in place and then performs an explicit re-submit."
| The edit lands BETWEEN the two, which moves the freshest relevant action off the rejection and onto the edit —
| so each round exercises BOTH causes of review-staleness in sequence, and the block reason changes underneath a
| still-blocked activation. The full history (both rejections with their distinct notes, both edits, both
| re-submits) survives in the append-only trail; the final activation records exactly one event.
*/

it('runs two rejection rounds with a real identity edit in each, blocking on both causes and preserving the full history', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    rearmProjectProducerActive(7);              // producer gate open — the review-freshness BLOCK is what must fire

    $master = rearmCreateDraftMaster($creator); // C creates the draft

    actingAs($reviewer, 'operator');
    app(SubmitProductMasterForReview::class)->handle($master);   // R submits — the sole `.submitted` row

    // ── Round 1 ───────────────────────────────────────────────────────────────────────────────────
    app(RejectProductMasterReview::class)->handle($master, 'Round 1: vintage missing from the label.');

    // Cause 1 — the un-remediated rejection.
    actingAs($approver, 'operator');
    expect(fn () => app(ActivateProductMaster::class)->handle($master->refresh()))
        ->toThrow(ApprovalGovernanceViolation::class, 'un-remediated');
    rearmExpectBlockedInReviewed($master);

    // The Creator remedies the rejection by editing in place. Still blocked — but the reason has MOVED: the
    // freshest relevant action is now the edit, not the rejection (latest-wins, not any-rejection-pending).
    rearmRenameMaster($creator, $master, 'Château Margaux 2015');

    actingAs($approver, 'operator');
    expect(fn () => app(ActivateProductMaster::class)->handle($master->refresh()))
        ->toThrow(ApprovalGovernanceViolation::class, 'edited');
    rearmExpectBlockedInReviewed($master);

    // One re-submit clears BOTH causes — the remedy never depends on which one armed the block.
    actingAs($creator, 'operator');
    app(ResubmitProductMasterForReview::class)->handle($master->refresh());

    // ── Round 2 ───────────────────────────────────────────────────────────────────────────────────
    // R rejects the re-submitted Master a second time, with a distinct note; the pair of causes repeats.
    actingAs($reviewer, 'operator');
    app(RejectProductMasterReview::class)->handle($master->refresh(), 'Round 2: provenance note still unclear.');

    actingAs($approver, 'operator');
    expect(fn () => app(ActivateProductMaster::class)->handle($master->refresh()))
        ->toThrow(ApprovalGovernanceViolation::class, 'un-remediated');
    rearmExpectBlockedInReviewed($master);

    rearmRenameMaster($creator, $master, 'Château Margaux Grand Vin 2015');

    actingAs($approver, 'operator');
    expect(fn () => app(ActivateProductMaster::class)->handle($master->refresh()))
        ->toThrow(ApprovalGovernanceViolation::class, 'edited');
    rearmExpectBlockedInReviewed($master);

    actingAs($creator, 'operator');
    app(ResubmitProductMasterForReview::class)->handle($master->refresh());

    // ── Final activation ──────────────────────────────────────────────────────────────────────────
    // Separation of duties still holds across four intervening non-governance-lineage rows: `creatorOf` = C (the
    // `*Created` event), `reviewerOf` = R (the latest `%.submitted` — an `.identity_updated` matches neither that
    // pattern nor `.resubmitted`), and A ∉ {C, R}.
    actingAs($approver, 'operator');
    $active = app(ActivateProductMaster::class)->handle($master->refresh());

    expect($active->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(DomainEvent::query()->where('name', 'ProductMasterActivated')->count())->toBe(1)
        // Two edits, two versions: creation left it at 1 — no lifecycle transition ever touches `version`.
        ->and(ProductMaster::findOrFail($master->id)->version)->toBe(3);

    // The whole append-only history, in order: nothing collapsed, nothing rewritten. Two rejections, two edits,
    // two re-submits, one activation.
    expect(rearmAuditActions($master))->toBe([
        'catalog.product_master.submitted',
        'catalog.product_master.rejected',
        'catalog.product_master.identity_updated',
        'catalog.product_master.resubmitted',
        'catalog.product_master.rejected',
        'catalog.product_master.identity_updated',
        'catalog.product_master.resubmitted',
        'catalog.product_master.activated',
    ]);

    // Both rejection rows keep their distinct notes and their acting reviewer (§ 4.3 — "the full rejection
    // history … preserved as part of the entity's permanent append-only audit record").
    $rejections = AuditRecord::query()
        ->where('action', 'catalog.product_master.rejected')
        ->orderBy('id')
        ->get();

    $firstAfter = $rejections[0]->after ?? [];      // narrow the nullable jsonb; keys read order-independently (PG reorders)
    $secondAfter = $rejections[1]->after ?? [];
    $rejectors = $rejections->pluck('actor_id')->all();

    expect($firstAfter['notes'] ?? null)->toBe('Round 1: vintage missing from the label.')
        ->and($secondAfter['notes'] ?? null)->toBe('Round 2: provenance note still unclear.')
        ->and($rejectors[0])->toEqual($reviewer->id)    // uncast bigint; loose compare spans engines
        ->and($rejectors[1])->toEqual($reviewer->id);

    // Both identity edits are the Creator's, and each carries the name it replaced — the old versions stay
    // retrievable from the trail (BR-Audit-1: deprecated, never deleted).
    $edits = AuditRecord::query()
        ->where('action', 'catalog.product_master.identity_updated')
        ->orderBy('id')
        ->get();

    $editors = $edits->pluck('actor_id')->all();

    expect($edits[0]->before ?? [])->toEqual(['name' => 'Château Margaux', 'version' => 1])
        ->and($edits[0]->after ?? [])->toEqual(['name' => 'Château Margaux 2015', 'version' => 2])
        ->and($edits[1]->before ?? [])->toEqual(['name' => 'Château Margaux 2015', 'version' => 2])
        ->and($edits[1]->after ?? [])->toEqual(['name' => 'Château Margaux Grand Vin 2015', 'version' => 3])
        ->and($editors[0])->toEqual($creator->id)
        ->and($editors[1])->toEqual($creator->id);
});
