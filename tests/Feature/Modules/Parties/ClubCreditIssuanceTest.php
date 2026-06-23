<?php

use App\Modules\Parties\Actions\IssueClubCredit;
use App\Modules\Parties\Enums\ClubCreditState;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\ClubCredit;
use App\Modules\Parties\Models\Profile;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the {@see IssueClubCredit} Action (change club-credit task 2.1; design L1/L2/L3/L4/L5; party-registry —
 * Requirement: Club Credit Issuance; Module K PRD § 11.1). It drives the REAL Action and asserts the two acceptance
 * bullets of the issuance writer:
 *   - the HAPPY PATH on a credit-generating Club: an `active` credit is created with `amount` = the Club `fee`
 *     VERBATIM (minor units + currency — full-fee → full-credit, K.18 deferred), `remaining` = `amount` (the K.17
 *     carry-forward starts full), `valid_from` the issuance instant and `valid_to` 31 December of the issuance year;
 *     the credit is the Profile's at-most-one `active` Club Credit (the within-module relation resolves it);
 *   - the STRUCTURAL ONE-ACTIVE guard: a re-issue while an `active` credit exists violates the partial unique index
 *     `(profile_id) WHERE state = 'active'` (design L1 — there is NO application-level pre-check), the transaction
 *     rolls back, and exactly the one original `active` credit remains.
 *
 * The issuance-precondition rejections (`generates_credit = false` / null `fee`), the Hold-asymmetry (issuance is
 * NOT Hold-gated) and the §11.4 no-`domain_events`-row assertion are the sibling task-2.2 matrix added to this file
 * next; this slice proves the writer's core contract. RefreshDatabase per the directory convention; the clock is
 * frozen so the validity window is deterministic on both engines. Money is asserted by `Money::equals()` against the
 * explicit fee given to the Club, and the window by the date-level `valid_to` idiom (tz-safe under the UTC app
 * timezone — the ClubCreditTest idiom, never an `equalTo` on the microsecond-bearing `endOfYear()`). The one-active
 * probe relies on IssueClubCredit's own `DB::transaction` being a SAVEPOINT under RefreshDatabase's wrapper, so
 * PostgreSQL's transaction-abort on the unique violation stays isolated and the count after the throw is valid — the
 * file holds on PostgreSQL 17.
 */
uses(RefreshDatabase::class);

// Reset the frozen clock after each test so the global test-now never leaks into a sibling (the SweepTest idiom).
afterEach(fn () => CarbonImmutable::setTestNow());

it('issues an active credit whose amount = the Club fee, remaining = amount, valid_to = 31 Dec of the issuance year', function () {
    $now = CarbonImmutable::parse('2026-06-23 12:00:00', 'UTC');
    CarbonImmutable::setTestNow($now);

    // The credit-generating Club is given an explicit fee, held in a non-null Money so the credit's amount can be
    // asserted equal to THE Club's fee verbatim (the factory default already sets `generates_credit = true`).
    $fee = Money::of(25000, Currency::EUR);
    $club = Club::factory()->create(['fee' => $fee]);
    $profile = Profile::factory()->create(['club_id' => $club->id]);

    $credit = app(IssueClubCredit::class)->handle($profile->id);

    // The issued credit is born `active`, `amount` = the Club fee VERBATIM (minor units + currency — design L2),
    // `remaining` = `amount` (carry-forward starts full), window = issuance instant → 31 Dec of the issuance year.
    expect($credit->state)->toBe(ClubCreditState::Active)
        ->and($credit->profile_id)->toBe($profile->id)
        ->and($credit->amount->equals($fee))->toBeTrue()
        ->and($credit->amount->minorUnits)->toBe(25000)
        ->and($credit->amount->currency)->toBe(Currency::EUR)
        ->and($credit->remaining->equals($credit->amount))->toBeTrue()
        ->and($credit->valid_from->equalTo($now))->toBeTrue()   // whole-second instant round-trips on both engines
        ->and($credit->valid_to->format('Y-m-d'))->toBe($now->endOfYear()->format('Y-m-d'));

    // Re-fetch so the assertions exercise the read/hydration casts (the persisted record, not the in-memory
    // create() values): the Money round-trips with no precision loss through the MoneyCast.
    $read = ClubCredit::findOrFail($credit->id);
    expect($read->amount->equals($fee))->toBeTrue()
        ->and($read->remaining->equals($fee))->toBeTrue()
        ->and($read->state)->toBe(ClubCreditState::Active)
        ->and($read->valid_to->format('Y-m-d'))->toBe($now->endOfYear()->format('Y-m-d'));

    // The credit is the Profile's at-most-one `active` Club Credit (the within-module relation resolves it).
    expect(Profile::findOrFail($profile->id)->activeClubCredit?->is($credit))->toBeTrue();
});

it('rejects a re-issue while an active credit exists (the structural one-active partial index)', function () {
    $club = Club::factory()->create();   // factory default: generates_credit = true, fee = 25000 EUR
    $profile = Profile::factory()->create(['club_id' => $club->id]);

    $first = app(IssueClubCredit::class)->handle($profile->id);
    expect($first->state)->toBe(ClubCreditState::Active);

    // A second issuance while an `active` credit exists violates the partial unique index
    // `(profile_id) WHERE state = 'active'` — the structural one-active guard (design L1; NO app-level pre-check).
    // IssueClubCredit wraps its work in DB::transaction, so under RefreshDatabase's outer transaction the index
    // violation aborts only that SAVEPOINT and PostgreSQL stays isolated (the ClubCreditTest one-active idiom).
    expect(fn () => app(IssueClubCredit::class)->handle($profile->id))
        ->toThrow(QueryException::class);

    // The rolled-back re-issue left exactly the one original `active` credit — replacing it requires
    // forfeit-before-issue (design L5; exercised in the forfeiture tests).
    expect(ClubCredit::query()->where('profile_id', $profile->id)->count())->toBe(1)
        ->and(ClubCredit::query()->where('profile_id', $profile->id)
            ->where('state', ClubCreditState::Active->value)->count())->toBe(1);
});
