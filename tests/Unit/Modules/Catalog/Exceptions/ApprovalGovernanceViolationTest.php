<?php

use App\Modules\Catalog\Exceptions\ApprovalGovernanceViolation;
use Tests\TestCase;

// Pins the single parameterized approval-governance guard exception (catalog-lifecycle-approval, design D5;
// catalog-review-freshness-resubmit RM-06, design D1; product-catalog — Requirement: Approval Governance).
// One class, the entity name a parameter, carries every approval-governance failure for the seven spine
// entities: the Creator → Reviewer → Approver separation-of-duties floor (copy from the `approval` group)
// PLUS the RM-06 review-freshness block-gate (copy from the `lifecycle` group, thrown here so it surfaces
// through the console kit's outcome path like the SoD floor). Booting the app (TestCase, NO RefreshDatabase —
// no DB is touched) makes the translator available so __() resolves lang/en/catalog.php instead of echoing
// the key back. The entity token ('ProductMaster') appears in no template, so its presence in a resolved
// message proves :entity was interpolated — not merely that the copy spells a similar word.

uses(TestCase::class);

it('is a RuntimeException so the operator console kit surfaces it via surfaceLifecycleOutcome', function () {
    // The kit's surfaceLifecycleOutcome catches RuntimeException to render an action_failed notification
    // (design D1/D5) — the block-gate reuses that path for free, so the base class is load-bearing.
    expect(new ApprovalGovernanceViolation(''))->toBeInstanceOf(RuntimeException::class);
});

it('rejects a governance step attempted with no operator principal, naming the entity', function () {
    $exception = ApprovalGovernanceViolation::requiresOperatorPrincipal('ProductMaster');

    expect($exception)->toBeInstanceOf(ApprovalGovernanceViolation::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('ProductMaster');
});

it('rejects the creator approving their own entity, naming the entity', function () {
    $exception = ApprovalGovernanceViolation::creatorMayNotApprove('ProductMaster');

    expect($exception)->toBeInstanceOf(ApprovalGovernanceViolation::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('ProductMaster');
});

it('rejects the reviewer approving the entity they reviewed, naming the entity', function () {
    $exception = ApprovalGovernanceViolation::reviewerMayNotApprove('ProductMaster');

    expect($exception)->toBeInstanceOf(ApprovalGovernanceViolation::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('ProductMaster');
});

it('rejects insufficient separation when creator and reviewer are the same operator, naming the entity', function () {
    $exception = ApprovalGovernanceViolation::insufficientSeparation('ProductMaster');

    expect($exception)->toBeInstanceOf(ApprovalGovernanceViolation::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('ProductMaster');
});

it('blocks activation while a rejection is pending (RM-06 review-freshness block-gate), naming the entity', function () {
    // The block-gate reason lives in the `lifecycle` group but is thrown from this class so it surfaces
    // through the console kit's outcome path like the SoD floor (design D1). 'ProductMaster' is absent from
    // the template, so its presence proves :entity was interpolated.
    $exception = ApprovalGovernanceViolation::activationBlockedByPendingRejection('ProductMaster');

    expect($exception)->toBeInstanceOf(ApprovalGovernanceViolation::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('ProductMaster');
});

it('blocks activation while an identity edit awaits re-review (the block-gate EDIT cause), naming the entity', function () {
    // The second cause of the same review-freshness block-gate (catalog-module-0-completeness-sweep design D4):
    // review-governed content changed after the last review decision. Same remedy (re-submit), different FACT.
    $exception = ApprovalGovernanceViolation::activationBlockedByUnreviewedEdit('ProductMaster');

    expect($exception)->toBeInstanceOf(ApprovalGovernanceViolation::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('ProductMaster');
});

it('gives the two review-freshness block causes distinct, discriminating copy', function () {
    // `un-remediated` and `edited` are the tokens the domain + console tests pin the two causes on. Each must
    // appear in exactly ONE reason, or a test asserting "the rejection block fired" would silently pass on the
    // edit block (and vice versa) — the assertion would stop discriminating.
    $rejection = ApprovalGovernanceViolation::activationBlockedByPendingRejection('ProductMaster')->getMessage();
    $edit = ApprovalGovernanceViolation::activationBlockedByUnreviewedEdit('ProductMaster')->getMessage();

    expect($rejection)->not->toBe($edit)
        ->and($rejection)->toContain('un-remediated')
        ->and($rejection)->not->toContain('edited')
        ->and($edit)->toContain('edited')
        ->and($edit)->not->toContain('un-remediated');
});

it('resolves every approval-governance lang key with the :entity placeholder wired', function (string $key) {
    // 'SomeEntity' appears in no template, so its presence in the resolved string proves :entity was
    // interpolated; a missing key would make Laravel echo the key back unchanged.
    $resolved = __($key, ['entity' => 'SomeEntity']);

    expect($resolved)->not->toBe($key)
        ->and($resolved)->toContain('SomeEntity');
})->with([
    'catalog.approval.requires_operator',
    'catalog.approval.self_approval_creator',
    'catalog.approval.self_approval_reviewer',
    'catalog.approval.insufficient_separation',
    // The RM-06 block-gate, both causes — copy lives in the `lifecycle` group, not `approval` (factory docblock).
    'catalog.lifecycle.activation_blocked_by_pending_rejection',
    'catalog.lifecycle.activation_blocked_by_unreviewed_edit',
]);
