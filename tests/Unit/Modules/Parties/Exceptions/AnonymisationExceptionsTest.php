<?php

use App\Modules\Parties\Exceptions\AnonymisationBlockedByComplianceHold;
use Tests\TestCase;

// Pins the localized right-to-erasure rejection copy (change parties-anonymisation, task 1.3; design D2;
// party-registry — Requirement: Anonymisation Hold Precedence; canon MVP-DEC-015 / ADR
// 2026-07-02-adopt-dec-015-anonymisation-hold-block-set; Module K PRD § 8.2 / AC-K-J-9a; invariant 12 — no
// hardcoded user-facing strings). `AnonymiseCustomer` (task 3.2) is ORTHOGONAL to the status FSM (anonymises
// from any status), IDEMPOTENT (a re-run is a no-op, not a throw) and has NO illegal-state edge, so its ONLY
// rejection is the Hold-precedence gate: it blocks iff an active `compliance` Hold covers the Customer. That
// single blocked-reason is the copy pinned here; the `AnonymiseCustomer` blocked-exception factory that
// resolves it lands with task 3.2 and will extend this file (the ClubCreditExceptionsTest pattern — the lang
// tests are co-located with the exception factory tests).
//
// Booting the app (TestCase, NO RefreshDatabase — no DB is touched) makes the translator available so __()
// resolves the lang/en/parties.php copy instead of echoing the key back. Sibling: ClubCreditExceptionsTest.

uses(TestCase::class);

it('resolves the compliance-Hold anonymisation-block reason with the :customer id wired, PII-free', function () {
    // The id (7777) is absent from the template, so its presence proves :customer was interpolated; a missing
    // key would make Laravel echo the key back. The customer id is an operator-facing reference (a digit, like
    // the sibling club_credit :credit / profile :customer ids) — NOT PII; an email ('@') would be, and the copy
    // names the rule without interpolating any name/email/phone/date-of-birth.
    $resolved = __('parties.anonymisation.blocked_by_compliance_hold', ['customer' => 7777]);

    expect($resolved)->not->toBe('parties.anonymisation.blocked_by_compliance_hold')
        ->and($resolved)->toContain('7777')
        ->and($resolved)->toContain('compliance');
    expect($resolved)->not->toContain('@');
});

it('builds the compliance-Hold anonymisation-block exception with the localized, PII-free reason', function () {
    // The AnonymiseCustomer (task 3.2) gate factory: it resolves the pinned copy with the Customer id interpolated
    // and stays log-safe — the customer id (7777) is an operator-facing reference, not PII, and no email ('@') or
    // other personal token leaks into the message. A missing key would echo the key back (asserted absent).
    $exception = AnonymisationBlockedByComplianceHold::forCustomer(7777);

    expect($exception)->toBeInstanceOf(RuntimeException::class)
        ->and($exception->getMessage())->toContain('7777')
        ->and($exception->getMessage())->toContain('compliance');
    expect($exception->getMessage())->not->toContain('@');
    expect($exception->getMessage())->not->toBe('parties.anonymisation.blocked_by_compliance_hold');
});

it('preserves the pre-existing parties lang groups', function () {
    // The anonymisation group is ADDED alongside the parties-core / lifecycle / compliance / membership / hold /
    // club_credit groups — not a rewrite; a pre-existing key from each register must still resolve. 'lifted' is
    // absent from the hold template, so its presence proves :state interpolated.
    expect(__('parties.customer.duplicate_email'))
        ->not->toBe('parties.customer.duplicate_email');

    expect(__('parties.hold.cannot_lift_not_active', ['state' => 'lifted']))
        ->not->toBe('parties.hold.cannot_lift_not_active')
        ->toContain('lifted');

    expect(__('parties.club_credit.over_application', ['credit' => 909]))
        ->not->toBe('parties.club_credit.over_application')
        ->toContain('909');
});
