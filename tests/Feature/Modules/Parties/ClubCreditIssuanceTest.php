<?php

use App\Modules\Parties\Actions\IssueClubCredit;
use App\Modules\Parties\Enums\ClubCreditState;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Exceptions\ClubCreditIssuancePrecondition;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\ClubCredit;
use App\Modules\Parties\Models\Hold;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\DomainEvent;
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
 * Task 2.2 completes the matrix: the two issuance-precondition rejections (`generates_credit = false` and null
 * `fee`), each asserted by exception CLASS {@see ClubCreditIssuancePrecondition} — the localized messages land in
 * task 5.2, so until then `__()` returns the key string and the class is the pinned contract — and each proven to
 * create NO row; the §11.2 Hold-asymmetry (an active Profile-scope Hold does NOT block issuance — issuance records
 * the entitlement and is not Hold-gated; only redemption is); and the §11.4 audit-only guarantee (the issue records
 * NO `domain_events` row — delta 0 across the call, this writer injecting no recorder). RefreshDatabase per the
 * directory convention; the clock is frozen so the validity window is deterministic on both engines. Money is
 * asserted by `Money::equals()` against the explicit fee given to the Club, and the window by the date-level
 * `valid_to` idiom (tz-safe under the UTC app timezone — the ClubCreditTest idiom, never an `equalTo` on the
 * microsecond-bearing `endOfYear()`). The one-active probe relies on IssueClubCredit's own `DB::transaction` being a
 * SAVEPOINT under RefreshDatabase's wrapper, so PostgreSQL's transaction-abort on the unique violation stays isolated
 * and the count after the throw is valid — the file holds on PostgreSQL 17.
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

it('refuses issuance for a Club that does not generate credit, creating no row (§ 11.1; AC-K-J-16)', function () {
    // generates_credit = false but a fee IS present, so it is unambiguously the credit-policy gate that fires
    // (Precondition 1), not the fee-null guard — a non-credit Club issues nothing.
    $club = Club::factory()->create(['generates_credit' => false, 'fee' => Money::of(25000, Currency::EUR)]);
    $profile = Profile::factory()->create(['club_id' => $club->id]);

    // Asserted by exception CLASS, not message — the localized i18n keys land in task 5.2 (until then `__()`
    // returns the key string); the pinned contract this slice proves is the precondition TYPE.
    expect(fn () => app(IssueClubCredit::class)->handle($profile->id))
        ->toThrow(ClubCreditIssuancePrecondition::class);

    // The precondition fires BEFORE any write (design L2) — no Club Credit row was created.
    expect(ClubCredit::query()->where('profile_id', $profile->id)->count())->toBe(0);
});

it('refuses issuance for a credit-generating Club with a null fee, creating no row (the fee-null guard; design L2)', function () {
    // generates_credit = true (factory default) but fee = null → the credit amount cannot be defined, so issuance
    // is refused rather than minting a zero/undefined credit (Precondition 2 — the fee-null guard).
    $club = Club::factory()->create(['fee' => null]);
    $profile = Profile::factory()->create(['club_id' => $club->id]);

    expect(fn () => app(IssueClubCredit::class)->handle($profile->id))
        ->toThrow(ClubCreditIssuancePrecondition::class);

    expect(ClubCredit::query()->where('profile_id', $profile->id)->count())->toBe(0);
});

it('issues a credit even when an active Hold covers the Profile — issuance is not Hold-gated (§ 11.2 asymmetry)', function () {
    $fee = Money::of(25000, Currency::EUR);
    $club = Club::factory()->create(['fee' => $fee]);
    $profile = Profile::factory()->create(['club_id' => $club->id]);

    // An active Profile-scope Hold (a pure fixture — HoldFactory bypasses PlaceHold, so no event/suspension
    // coupling): exactly the kind of restriction that gates REDEMPTION (§ 11.2). Issuance, by contrast, records the
    // entitlement once the fee is paid and consults NEITHER Holds NOR Profile state (the Action has no such
    // precondition — the IssueClubCredit docblock), so it must still mint the credit. The § 11.2 asymmetry.
    Hold::factory()->create([
        'hold_type' => HoldType::Fraud,
        'scope_type' => HoldScope::Profile,
        'scope_id' => $profile->id,
    ]);

    $credit = app(IssueClubCredit::class)->handle($profile->id);

    expect($credit->state)->toBe(ClubCreditState::Active)
        ->and($credit->profile_id)->toBe($profile->id)
        ->and($credit->amount->equals($fee))->toBeTrue()
        ->and(Profile::findOrFail($profile->id)->activeClubCredit?->is($credit))->toBeTrue();
});

it('records no domain event — issuance is audit-only (§ 11.4; design L3)', function () {
    $club = Club::factory()->create();
    $profile = Profile::factory()->create(['club_id' => $club->id]);

    // The factories bypass the Actions, so the event log starts empty; snapshot to assert the DELTA across the
    // issue is exactly 0 (not merely a final count, so the assertion stays honest if a fixture ever emits).
    $before = DomainEvent::query()->count();

    $credit = app(IssueClubCredit::class)->handle($profile->id);
    expect($credit->state)->toBe(ClubCreditState::Active);   // the issue actually happened

    // § 11.4 makes `ClubCreditIssued` / the upstream `MembershipFeePaid` MODULE E's events; this within-module
    // writer records NONE — it injects no DomainEventRecorder (mirrors SuspendAccount/RecordKycVerified). Delta = 0.
    expect(DomainEvent::query()->count())->toBe($before);
});
