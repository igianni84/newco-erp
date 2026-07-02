<?php

use App\Modules\Parties\Exceptions\InvalidAddressCountryCode;
use Tests\TestCase;

// Pins the localized Customer-Address rejection copy (change parties-anonymisation, task 2.1; design D4;
// party-registry — Requirement: Customer Address; invariant 12 — no hardcoded user-facing strings). The thin
// CreateCustomerAddress action validates `country_code` at the boundary (ISO 3166-1 alpha-2, fail-closed) and
// throws InvalidAddressCountryCode with the localized reason pinned here; the resolution proves the key exists
// (not echoed) and interpolates the offending code. Sibling: AnonymisationExceptionsTest / ClubCreditExceptionsTest
// (the lang-resolution tests co-locate with the exception factories).
//
// Booting the app (TestCase, NO RefreshDatabase — no DB is touched) makes the translator available so __()
// resolves the lang/en/parties.php copy instead of echoing the key back.

uses(TestCase::class);

it('resolves the invalid-country-code reason with the :country value interpolated, PII-free', function () {
    // The bad code 'ITA' is absent from the template, so its presence proves :country was interpolated; a missing
    // key would make Laravel echo the key back. A country code is an operator-supplied token, NOT PII (unlike an
    // email, which would carry an '@'), so it is safe to interpolate and the reason may reach logs.
    $exception = InvalidAddressCountryCode::forCode('ITA');
    $resolved = $exception->getMessage();

    expect($resolved)->not->toBe('parties.address.invalid_country_code')
        ->and($resolved)->toContain('ITA')
        ->and($resolved)->toContain('ISO 3166');
    expect($resolved)->not->toContain('@');
});

it('preserves the pre-existing parties lang groups', function () {
    // The address group is ADDED alongside the parties-core / lifecycle / compliance / membership / hold /
    // club_credit / anonymisation groups — not a rewrite; a pre-existing key from each register must still resolve.
    expect(__('parties.customer.duplicate_email'))
        ->not->toBe('parties.customer.duplicate_email');

    expect(__('parties.anonymisation.blocked_by_compliance_hold', ['customer' => 42]))
        ->not->toBe('parties.anonymisation.blocked_by_compliance_hold')
        ->toContain('42');

    expect(__('parties.club_credit.over_application', ['credit' => 909]))
        ->not->toBe('parties.club_credit.over_application')
        ->toContain('909');
});
