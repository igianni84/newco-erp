<?php

use App\Modules\Parties\Support\AnonymisedPlaceholders;

// Pins the deterministic anonymisation placeholder derivation (change parties-anonymisation, task 3.1; design D1;
// party-registry — Requirement: Customer Anonymisation; AC-K-J-9 / FSM-16). AnonymisedPlaceholders is the pure
// value object the `AnonymiseCustomer` action (task 3.2) reads to overwrite a Customer's PII and every scoped
// Address's personal fields. It is DETERMINISTIC and id-keyed, never random/faker (design D1), which earns two
// invariants at once: the id in the email placeholder PRESERVES the globally-unique `parties_customers.email`
// index (BR-K-Identity-1 — anonymising two Customers never collides), and the whole operation is reproducible.
//
// A pure value object: no DB, no container, no translator — so this is a plain unit test with NO uses(TestCase)
// and NO RefreshDatabase (tests/Pest.php binds TestCase to Feature only). Sibling style: Platform/Money/MoneyTest.

it('derives a deterministic id-keyed email placeholder that preserves the UNIQUE email invariant', function () {
    // The exact contract (design D1): `anonymised+{id}@anonymised.invalid`.
    expect(AnonymisedPlaceholders::for(7)->email)->toBe('anonymised+7@anonymised.invalid');

    // UNIQUE-safe (design D1 Risks): two Customers anonymised must not collide on the globally-unique
    // `parties_customers.email` index — the id keying is what makes each placeholder distinct (a random email
    // could collide, the rejected alternative).
    expect(AnonymisedPlaceholders::for(7)->email)
        ->not->toBe(AnonymisedPlaceholders::for(8)->email);
});

it('derives an id-keyed, PII-free name placeholder', function () {
    // A stable, PII-free breadcrumb an operator can still refer to by id (design D1) — no real name survives.
    expect(AnonymisedPlaceholders::for(7)->name)->toBe('Anonymised Customer 7');

    expect(AnonymisedPlaceholders::for(7)->name)
        ->not->toBe(AnonymisedPlaceholders::for(8)->name);
});

it('is fully deterministic — repeated derivation for an id is identical (never random/faker)', function () {
    // Reproducibility is the whole point (design D1): the same id ALWAYS yields the same placeholders, so the
    // overwrite is testable and idempotent. Two independent derivations for id 42 must be byte-identical.
    expect(AnonymisedPlaceholders::for(42)->customerAttributes())
        ->toBe(AnonymisedPlaceholders::for(42)->customerAttributes());

    expect(AnonymisedPlaceholders::for(42)->addressAttributes())
        ->toBe(AnonymisedPlaceholders::for(42)->addressAttributes());
});

it('projects the Customer PII overwrite map — email/name derived, phone/date_of_birth nulled', function () {
    // The exact DB-column => placeholder map `AnonymiseCustomer` (task 3.2) writes over the four personal-data
    // columns: `email`/`name` id-derived; the two NULLABLE columns (`phone`, `date_of_birth`) erased to NULL.
    $attributes = AnonymisedPlaceholders::for(7)->customerAttributes();

    expect($attributes)->toBe([
        'email' => 'anonymised+7@anonymised.invalid',
        'name' => 'Anonymised Customer 7',
        'phone' => null,
        'date_of_birth' => null,
    ]);
    // The complete personal-column set — a future PII column added to the Customer must extend this map.
    expect($attributes)->toHaveCount(4);
});

it('projects the Address overwrite map — NOT-NULL fields sentinelled, nullable fields nulled', function () {
    // The exact map `AnonymiseCustomer` writes over EVERY scoped Address's eight personal fields (design D1/D4).
    // NOT-NULL free-text fields (`line1`/`locality`/`postal_code`) take the sentinel; `country_code` takes the
    // ISO 3166-1 alpha-2 "unknown" code; the four nullable fields are erased to NULL.
    $attributes = AnonymisedPlaceholders::for(7)->addressAttributes();

    expect($attributes)->toBe([
        'line1' => 'Anonymised',
        'line2' => null,
        'locality' => 'Anonymised',
        'region' => null,
        'postal_code' => 'Anonymised',
        'country_code' => 'ZZ',
        'company_name' => null,
        'vat_id' => null,
    ]);
    // All eight `parties_addresses` personal/company fields — a future field must extend this map.
    expect($attributes)->toHaveCount(8);
});

it('erases the Address with a constant sentinel carrying no re-identification vector', function () {
    // The Address placeholders are CONSTANT, not id-keyed (design D1): `parties_addresses` has no unique
    // constraint, so a shared sentinel both erases the data and leaks no id-linkable value. Two different
    // Customers' anonymised Addresses are byte-identical.
    expect(AnonymisedPlaceholders::for(7)->addressAttributes())
        ->toBe(AnonymisedPlaceholders::for(8)->addressAttributes());
});

it('keeps the country_code placeholder a Postgres-string(2)-safe ISO 3166-1 alpha-2 code', function () {
    // Cross-engine guard: `parties_addresses.country_code` is `string(2)` = varchar(2) on Postgres, which REJECTS
    // a value over two chars (SQLite silently allows it — the exact cross-engine trap this change must avoid). The
    // sentinel must also satisfy the CreateCustomerAddress `/^[A-Z]{2}$/` format law so the row stays well-formed.
    $countryCode = AnonymisedPlaceholders::for(7)->addressAttributes()['country_code'];

    expect(mb_strlen($countryCode))->toBeLessThanOrEqual(2)
        ->and(preg_match('/^[A-Z]{2}$/', $countryCode))->toBe(1);
});

it('routes the placeholder email to the RFC 6761 non-routable .invalid TLD', function () {
    // The `.invalid` TLD is reserved and guaranteed non-resolvable (RFC 6761), so an anonymised placeholder email
    // can never reach a real inbox (design D1).
    expect(AnonymisedPlaceholders::for(7)->email)->toEndWith('@anonymised.invalid');
});
