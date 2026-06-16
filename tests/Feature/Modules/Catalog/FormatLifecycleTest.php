<?php

use App\Modules\Catalog\Actions\ActivateFormat;
use App\Modules\Catalog\Actions\CreateFormat;
use App\Modules\Catalog\Actions\RejectFormatReview;
use App\Modules\Catalog\Actions\ReopenFormat;
use App\Modules\Catalog\Actions\RetireFormat;
use App\Modules\Catalog\Actions\SubmitFormatForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use App\Modules\Catalog\Exceptions\IllegalLifecycleTransition;
use App\Modules\Catalog\Models\Format;
use App\Modules\OperatorPanel\Models\Operator;
use App\Platform\Audit\AuditRecord;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;

use function Pest\Laravel\actingAs;

/**
 * Pins the Format lifecycle (catalog-lifecycle-approval task 4.1; design D1/D5/D9; product-catalog —
 * Requirements: Product Lifecycle State Machine, Approval Governance, Product Lifecycle Events). Format is
 * the FIRST of the six remaining spine entities to gain its transitions through the shared
 * `LifecycleTransition` mechanism, and the first STANDALONE one: it has no parent in the hierarchy, so its
 * activation carries NO activation gate — only the approval governance.
 *
 * The shared mechanism's internals (the locked from-state re-read, the audit envelope, the governance
 * lineage read) are exhaustively pinned by ProductMasterLifecycleTest; these tests prove the FORMAT WIRING:
 * each of the five Actions drives the mechanism for Format, the activation is governed by approval ONLY (no
 * parent gate), and the `reviewed → active` / `active → retired` steps record `FormatActivated` /
 * `FormatRetired` (the audit-only `draft → reviewed` / `retired → reviewed` checkpoints record neither).
 *
 * DatabaseMigrations (per the section-4 standing rule + design D11): the mechanism opens its OWN
 * DB::transaction, so the recorder's `transactionLevel() === 0` guard sees a REAL commit (the faithful
 * production shape). Each step authenticates a distinct operator with actingAs(), so the resolved actor on
 * each audit row / event is (newco_ops, that operator's id).
 */
uses(DatabaseMigrations::class);

/**
 * Create a draft Format as $creator through the real CreateFormat Action — recording `FormatCreated` with
 * $creator's actor_id, the creator lineage the governance guard reads. Leaves $creator as the acting
 * principal (the caller switches before the next governance step). Distinctly named to avoid colliding with
 * ProductMasterLifecycleTest's global `lifecycleCreateDraftMaster` (one shared Pest namespace).
 */
function lifecycleCreateDraftFormat(Operator $creator, string $name = 'Magnum', string $sizeLabel = '1.5L', int $volumeMl = 1500): Format
{
    actingAs($creator, 'operator');

    return app(CreateFormat::class)->handle(name: $name, sizeLabel: $sizeLabel, volumeMl: $volumeMl);
}

it('submits a draft Format to reviewed, recording one audit row and no domain event', function () {
    $operator = Operator::factory()->create();

    $format = lifecycleCreateDraftFormat($operator);

    $reviewed = app(SubmitFormatForReview::class)->handle($format);

    // State moved draft → reviewed — assert the returned model AND the persisted row.
    expect($reviewed->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(Format::findOrFail($format->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // Exactly one audit row for the submit, carrying the lifecycle edge + the operator principal.
    $audit = AuditRecord::query()->where('action', 'catalog.format.submitted')->sole();

    expect($audit->module)->toBe('catalog')
        ->and($audit->entity_type)->toBe('Format')                 // matches the domain-event entity_type
        ->and($audit->entity_id)->toBe((string) $format->id)       // envelope entity_id is a string
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)       // resolved from ActorContext (operator guard)
        ->and($audit->actor_id)->toEqual($operator->id)            // uncast bigint; loose compare spans engines
        ->and($audit->before)->toBe(['lifecycle_state' => 'draft'])
        ->and($audit->after)->toBe(['lifecycle_state' => 'reviewed'])
        ->and($audit->authorization_basis)->toBe('catalog-lifecycle');

    // The submit checkpoint is event-silent: no *Activated, no *Reviewed (the next event is the activation).
    expect(DomainEvent::query()->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0);
});

it('activates a reviewed Format to active under approval governance only, recording one FormatActivated', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    // Three distinct operators: a standalone Format has no parent gate, so the approval governance
    // (Creator → Reviewer → Approver, role_count 3 default) is the SOLE activation precondition.
    $format = lifecycleCreateDraftFormat($creator);
    actingAs($reviewer, 'operator');
    app(SubmitFormatForReview::class)->handle($format);

    actingAs($approver, 'operator');
    $active = app(ActivateFormat::class)->handle($format);

    // State moved reviewed → active (returned model + persisted row) + one activation audit row.
    expect($active->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(Format::findOrFail($format->id)->lifecycle_state)->toBe(LifecycleState::Active)
        ->and(AuditRecord::query()->where('action', 'catalog.format.activated')->count())->toBe(1);

    // Exactly one FormatActivated, recorded in the writing transaction — module catalog, the entity envelope,
    // the approver principal, and a PII-free payload (id + post-transition active value only, no descriptors).
    $event = DomainEvent::query()->where('name', 'FormatActivated')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('Format')
        ->and($event->entity_id)->toBe((string) $format->id)
        ->and($event->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($event->actor_id)->toEqual($approver->id)            // uncast bigint — loose compare spans engines
        ->and($event->payload['format_id'] ?? null)->toEqual($format->id)
        ->and($event->payload['lifecycle_state'] ?? null)->toBe('active')
        ->and($event->payload)->not->toHaveKey('name');            // PII-free (no descriptive measure fields)
});

it('rejects self-approval by the creator (governance applies even with no parent gate)', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();

    $format = lifecycleCreateDraftFormat($creator);
    actingAs($reviewer, 'operator');
    app(SubmitFormatForReview::class)->handle($format);

    // The creator attempts the approval step — rejected on the no-self-approval floor (default role_count 3),
    // proving the standalone Format's activation is still governed by approval (the gate's absence ≠ no guard).
    actingAs($creator, 'operator');
    expect(fn () => app(ActivateFormat::class)->handle($format))
        ->toThrow(ApprovalGovernanceViolation::class, 'creator');

    expect(Format::findOrFail($format->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(AuditRecord::query()->where('action', 'catalog.format.activated')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'FormatActivated')->count())->toBe(0);
});

it('rejects activation on a non-reviewed Format via the from-state guard', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A draft Format: activate is valid only from reviewed, so the from-state guard fires (the message names
    // the locked from-state) — and a standalone activation records no event when rejected.
    $format = Format::factory()->create(['lifecycle_state' => LifecycleState::Draft]);

    expect(fn () => app(ActivateFormat::class)->handle($format))
        ->toThrow(IllegalLifecycleTransition::class, 'draft');

    expect(Format::findOrFail($format->id)->lifecycle_state)->toBe(LifecycleState::Draft)
        ->and(DomainEvent::query()->where('name', 'FormatActivated')->count())->toBe(0);
});

it('rejects a submit on a non-draft Format, naming the offending state, and writes nothing', function () {
    actingAs(Operator::factory()->create(), 'operator');

    $format = Format::factory()->create(['lifecycle_state' => LifecycleState::Reviewed]);

    // Out-of-state: submit is valid only from draft. The message names the locked from-state (reviewed).
    expect(fn () => app(SubmitFormatForReview::class)->handle($format))
        ->toThrow(IllegalLifecycleTransition::class, 'reviewed');

    expect(Format::findOrFail($format->id)->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(AuditRecord::query()->count())->toBe(0);
});

it('retires an active Format to retired, recording one FormatRetired', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();
    $approver = Operator::factory()->create();

    $format = lifecycleCreateDraftFormat($creator);
    actingAs($reviewer, 'operator');
    app(SubmitFormatForReview::class)->handle($format);
    actingAs($approver, 'operator');
    app(ActivateFormat::class)->handle($format);

    // Retire (active → retired): commercial-impact (operator floor), no activation gate.
    $retired = app(RetireFormat::class)->handle($format);

    expect($retired->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(Format::findOrFail($format->id)->lifecycle_state)->toBe(LifecycleState::Retired)
        ->and(AuditRecord::query()->where('action', 'catalog.format.retired')->count())->toBe(1);

    $event = DomainEvent::query()->where('name', 'FormatRetired')->sole();

    expect($event->module)->toBe('catalog')
        ->and($event->entity_type)->toBe('Format')
        ->and($event->entity_id)->toBe((string) $format->id)
        ->and($event->actor_id)->toEqual($approver->id)
        ->and($event->payload['format_id'] ?? null)->toEqual($format->id)
        ->and($event->payload['lifecycle_state'] ?? null)->toBe('retired')
        ->and($event->payload)->not->toHaveKey('name');
});

it('reopens a retired Format to reviewed, recording one audit row and no domain event', function () {
    actingAs(Operator::factory()->create(), 'operator');

    // A retired Format via the factory (it bypasses the FSM — a pure fixture).
    $format = Format::factory()->create(['lifecycle_state' => LifecycleState::Retired]);

    $reviewed = app(ReopenFormat::class)->handle($format);

    expect($reviewed->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(Format::findOrFail($format->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    $audit = AuditRecord::query()->where('action', 'catalog.format.reopened')->sole();

    expect($audit->entity_type)->toBe('Format')
        ->and($audit->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($audit->before)->toBe(['lifecycle_state' => 'retired'])
        ->and($audit->after)->toBe(['lifecycle_state' => 'reviewed']);

    // Reopen is event-silent — no *Activated / *Retired / *Reviewed recorded for the step.
    expect(DomainEvent::query()->where('name', 'like', '%Activated%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Retired%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0);
});

it('records a review rejection with notes, keeps the Format in reviewed, and preserves prior audit rows', function () {
    $creator = Operator::factory()->create();
    $reviewer = Operator::factory()->create();

    $format = lifecycleCreateDraftFormat($creator);
    actingAs($reviewer, 'operator');
    app(SubmitFormatForReview::class)->handle($format); // the prior (submit) audit row

    $rejected = app(RejectFormatReview::class)->handle($format, 'Volume does not match the stated size label.');

    // Stays in reviewed — there is no revert to draft (§ 4.3).
    expect($rejected->lifecycle_state)->toBe(LifecycleState::Reviewed)
        ->and(Format::findOrFail($format->id)->lifecycle_state)->toBe(LifecycleState::Reviewed);

    // One rejection audit row carrying the decision + notes + the acting reviewer principal.
    $rejection = AuditRecord::query()->where('action', 'catalog.format.rejected')->sole();
    $after = $rejection->after ?? []; // narrow the nullable jsonb to an array; keys asserted order-independently (PG jsonb reorders)

    expect($rejection->entity_type)->toBe('Format')
        ->and($rejection->entity_id)->toBe((string) $format->id)
        ->and($rejection->actor_role)->toBe(ActorRole::NewcoOps)
        ->and($rejection->actor_id)->toEqual($reviewer->id)
        ->and($rejection->before)->toBe(['lifecycle_state' => 'reviewed'])
        ->and($after['lifecycle_state'] ?? null)->toBe('reviewed')
        ->and($after['decision'] ?? null)->toBe('rejected')
        ->and($after['notes'] ?? null)->toBe('Volume does not match the stated size label.')
        ->and($rejection->authorization_basis)->toBe('catalog-lifecycle');

    // The earlier submit audit row is intact (append-only) and no domain event was recorded for the rejection.
    expect(AuditRecord::query()->where('action', 'catalog.format.submitted')->count())->toBe(1)
        ->and(DomainEvent::query()->where('name', 'like', '%Reviewed%')->count())->toBe(0)
        ->and(DomainEvent::query()->where('name', 'FormatActivated')->count())->toBe(0);
});
