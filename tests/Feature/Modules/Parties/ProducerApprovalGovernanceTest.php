<?php

use App\Modules\Parties\Actions\CreateProducer;
use App\Modules\Parties\Exceptions\SeparationOfDutiesViolation;
use App\Modules\Parties\Governance\ProducerApprovalGovernance;
use App\Modules\Parties\Models\Producer;
use App\Platform\Events\ActorContext;
use App\Platform\Events\ActorRole;
use App\Platform\Events\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the Parties-local separation-of-duties guard for Producer activation (change
 * parties-producer-approval-sod, task 1.3; design D2/D3/D4; party-registry — Requirement: Producer
 * Lifecycle; Module K PRD § 4.4 / AC-K-J-10; Admin Panel PRD § 5.2). The guard mirrors Catalog's
 * `ApprovalGovernance` at the spec-admissible 2-step Creator → Approver
 * depth (no reviewer leg — the Producer FSM is linear), reading ONLY the platform substrate: the
 * {@see ActorContext} for the acting approver and the `domain_events` log for the creator lineage. No
 * `Catalog\*` symbol is touched (CLAUDE.md invariant 10) — the guard is exercised here in isolation, before
 * task 2.1 wires it into `ActivateProducer`.
 *
 * The creator lineage is seeded through the REAL {@see CreateProducer} Action under a scoped
 * {@see ActorContext::runAs} (the RM-07 pattern), so the earliest `domain_events` row for the Producer
 * carries the creator's `actor_id` exactly as production would — a `factory()->create()` Producer records no
 * event and so has a VACUOUS (null) creator (the fourth case). `runAs` restores the prior (empty) context on
 * exit, so the system/null-actor case sees no lingering authenticated principal.
 *
 * RefreshDatabase: the guard reads committed `domain_events` rows written by the Action's own transaction;
 * the guard itself performs no write, so there is no state to roll back — the assertions are on the thrown
 * violation (or its absence) and the acting/creator identities, cross-engine (SQLite here; PostgreSQL 17 in
 * the close-ritual run — the `normalizeActorId` coercion is what keeps the `===` distinctness holding on
 * both engines).
 */
uses(RefreshDatabase::class);

/**
 * Create a draft Producer through the real {@see CreateProducer} Action as operator $creatorId, so its
 * `ProducerCreated` event carries that actor_id — the creator lineage the
 * guard recovers from `domain_events` (design D3). Scoped via {@see ActorContext::runAs} so no authenticated
 * principal lingers into the assertion.
 */
function producerSodDraft(int $creatorId): Producer
{
    return app(ActorContext::class)->runAs(ActorRole::NewcoOps, $creatorId, fn (): Producer => app(CreateProducer::class)->handle(
        name: 'Domaine Leflaive',
        region: 'Burgundy',
        country: 'FR',
    ));
}

it('rejects a system/null actor on the operator-principal floor, before the distinctness check', function () {
    // A genuine creator lineage exists (operator 101), but activation is attempted with NO authenticated
    // operator → ActorContext resolves (System, null). The operator-principal floor (design D4) fires FIRST,
    // so the violation is `requires_operator_principal`, not `creator_may_not_approve` — this pins the
    // in-guard order and closes the verdict's "System actor accepted" hole.
    $producer = producerSodDraft(101);

    expect(fn () => app(ProducerApprovalGovernance::class)->guard('Producer', $producer->id))
        ->toThrow(
            SeparationOfDutiesViolation::class,
            (string) __('parties.approval.requires_operator_principal', ['entity' => 'Producer']),
        );
});

it('rejects when the approving operator is the Producer creator (creator may not approve)', function () {
    // Operator 101 created the Producer and now attempts to activate it → the distinct-actor floor is
    // breached (approver === creator), so the guard throws `creator_may_not_approve` (design D1/D3).
    $producer = producerSodDraft(101);

    app(ActorContext::class)->runAs(ActorRole::NewcoOps, 101, function () use ($producer) {
        expect(fn () => app(ProducerApprovalGovernance::class)->guard('Producer', $producer->id))
            ->toThrow(
                SeparationOfDutiesViolation::class,
                (string) __('parties.approval.creator_may_not_approve', ['entity' => 'Producer']),
            );
    });
});

it('passes when a distinct operator approves a Producer created by another', function () {
    // Operator 101 created it; operator 202 activates → distinct actors, so the floor is satisfied and the
    // guard returns void (the 2-step Creator → Approver happy path — AC-K-J-10 at the configured depth).
    $producer = producerSodDraft(101);

    expect(fn () => app(ActorContext::class)->runAs(ActorRole::NewcoOps, 202, fn () => app(ProducerApprovalGovernance::class)->guard('Producer', $producer->id)))
        ->not->toThrow(SeparationOfDutiesViolation::class);
});

it('passes when the creator is null (no ProducerCreated lineage) and an operator approves', function () {
    // A factory Producer bypasses the Action, so it records NO ProducerCreated → the creator is null: a
    // vacuous distinctness (design D3). The operator-principal floor still holds (operator 303 is
    // authenticated), so the guard passes — a system/seed-created Producer stays activatable by one operator.
    $producer = Producer::factory()->create();
    expect(DomainEvent::query()->where('entity_type', 'Producer')->count())->toBe(0);

    expect(fn () => app(ActorContext::class)->runAs(ActorRole::NewcoOps, 303, fn () => app(ProducerApprovalGovernance::class)->guard('Producer', $producer->id)))
        ->not->toThrow(SeparationOfDutiesViolation::class);
});
