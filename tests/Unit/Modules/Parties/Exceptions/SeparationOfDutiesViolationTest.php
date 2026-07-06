<?php

use App\Modules\Parties\Exceptions\SeparationOfDutiesViolation;
use Tests\TestCase;

// Pins the Parties separation-of-duties guard exception (change parties-producer-approval-sod, task 1.2;
// design D1/D4; party-registry — Requirement: Producer Lifecycle; Module K PRD § 4.4 / AC-K-J-10). One
// parameterized class carries both failure modes of the Producer activation SoD floor: the missing
// operator-principal reject and the creator-self-approval reject. It mirrors Catalog's
// ApprovalGovernanceViolation MINUS the reviewer leg (the Producer FSM is linear — no `reviewed` state, so
// no reviewer/insufficient-separation factories). Booting the app (TestCase, NO RefreshDatabase — no DB is
// touched) makes the translator available so __() resolves lang/en/parties.php's `approval` group instead of
// echoing the key back. The entity token ('Producer') appears in no template, so its presence in a resolved
// message proves :entity was interpolated — not merely that the copy spells a similar word.

uses(TestCase::class);

it('is a RuntimeException so the operator console kit surfaces it via its outcome path', function () {
    // The OperatorPanel activate verb (task 3.1) catches RuntimeException to render an action_failed
    // notification without changing state — the base class is load-bearing, as it is for the Catalog sibling.
    expect(new SeparationOfDutiesViolation(''))->toBeInstanceOf(RuntimeException::class);
});

it('rejects an activation attempted with no operator principal, naming the entity', function () {
    $exception = SeparationOfDutiesViolation::requiresOperatorPrincipal('Producer');

    expect($exception)->toBeInstanceOf(SeparationOfDutiesViolation::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('Producer');
});

it('rejects the creator activating the entity they created, naming the entity', function () {
    $exception = SeparationOfDutiesViolation::creatorMayNotApprove('Producer');

    expect($exception)->toBeInstanceOf(SeparationOfDutiesViolation::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('Producer');
});

it('resolves every approval-governance lang key with the :entity placeholder wired', function (string $key) {
    // 'SomeEntity' appears in no template, so its presence in the resolved string proves :entity was
    // interpolated; a missing key would make Laravel echo the key back unchanged.
    $resolved = __($key, ['entity' => 'SomeEntity']);

    expect($resolved)->not->toBe($key)
        ->and($resolved)->toContain('SomeEntity');
})->with([
    'parties.approval.requires_operator_principal',
    'parties.approval.creator_may_not_approve',
]);
