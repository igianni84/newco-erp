<?php

use App\Modules\Parties\Actions\ApplyClubCredit;
use App\Modules\Parties\Enums\ClubCreditState;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Exceptions\ClubCreditRedemptionPrecondition;
use App\Modules\Parties\Exceptions\IllegalClubCreditTransition;
use App\Modules\Parties\Models\ClubCredit;
use App\Modules\Parties\Models\Profile;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the {@see ApplyClubCredit} Action (change club-credit task 3.1; design L3/L4/L6; party-registry —
 * Requirement: Club Credit Redemption and Carry-Forward; Module K PRD § 11.2 / § 11 / § 10.1). It drives the REAL
 * Action and asserts the acceptance bullets of the redemption writer:
 *   - the K.17 CARRY-FORWARD pair: a PARTIAL redemption decrements `remaining` and keeps the credit `active` (the
 *     balance carries forward — § 11 K.17), and a FULL redemption zeroes `remaining` and transitions
 *     `active → redeemed` (the sole `active → redeemed` writer);
 *   - the FOUR pre-write guards, each rejecting BEFORE any write so `remaining`/`state` are left unchanged: the FSM
 *     from-state guard (a non-`active` credit → {@see IllegalClubCreditTransition}); currency mismatch, over-
 *     application and the frozen-while-suspended freeze (all → {@see ClubCreditRedemptionPrecondition}).
 *
 * Rejections are asserted by exception CLASS, not message — the localized `parties.club_credit.*` keys land in task
 * 5.2, so until then `__()` returns the key string and the class is the pinned contract (the issuance-test idiom).
 * Task 3.2 completes the matrix with the freeze-THEN-restore round-trip (redemption succeeds once the Profile is
 * restored to `Active`) and the § 11.4 audit-only guarantee (the redemption records no `domain_events` row —
 * delta 0). RefreshDatabase per the directory convention; redemption touches only the credit + its Profile (no
 * clock-sensitive validity window is asserted, so no frozen clock is needed). The factories bypass the Actions, so
 * each credit is a pure fixture; Money is asserted via `Money::equals()` / the integer `minorUnits`, never a float
 * (invariant 6). Each Action opens its OWN `DB::transaction` (a SAVEPOINT under RefreshDatabase's wrapper), so the
 * file holds on PostgreSQL 17 as well as SQLite.
 */
uses(RefreshDatabase::class);

it('partially redeems a credit, reducing remaining and keeping it active (K.17 carry-forward)', function () {
    // an `active` credit with `remaining` 25000 EUR.
    $credit = ClubCredit::factory()->create([
        'amount' => Money::of(25000, Currency::EUR),
        'remaining' => Money::of(25000, Currency::EUR),
    ]);

    $applied = app(ApplyClubCredit::class)->handle($credit->id, Money::of(9000, Currency::EUR));

    // remaining 25000 − 9000 = 16000 EUR; the credit stays `active` — the balance carries forward (K.17).
    expect($applied->state)->toBe(ClubCreditState::Active)
        ->and($applied->remaining->equals(Money::of(16000, Currency::EUR)))->toBeTrue();

    // re-fetch so the assertion exercises the persisted record (the MoneyCast round-trip), not the in-memory model.
    $read = ClubCredit::findOrFail($credit->id);
    expect($read->state)->toBe(ClubCreditState::Active)
        ->and($read->remaining->equals(Money::of(16000, Currency::EUR)))->toBeTrue()
        ->and($read->amount->equals(Money::of(25000, Currency::EUR)))->toBeTrue();   // `amount` is untouched by redemption
});

it('fully redeems a credit, zeroing remaining and transitioning to redeemed', function () {
    // an `active` credit with `remaining` 16000 EUR.
    $credit = ClubCredit::factory()->create([
        'amount' => Money::of(16000, Currency::EUR),
        'remaining' => Money::of(16000, Currency::EUR),
    ]);

    $applied = app(ApplyClubCredit::class)->handle($credit->id, Money::of(16000, Currency::EUR));

    // remaining becomes zero → the credit transitions `active → redeemed` (fully spent).
    expect($applied->state)->toBe(ClubCreditState::Redeemed)
        ->and($applied->remaining->minorUnits)->toBe(0)
        ->and($applied->remaining->currency)->toBe(Currency::EUR);

    $read = ClubCredit::findOrFail($credit->id);
    expect($read->state)->toBe(ClubCreditState::Redeemed)
        ->and($read->remaining->minorUnits)->toBe(0);
});

it('rejects redemption of a non-active credit (the FSM from-state guard), leaving it unchanged', function () {
    // a fully-redeemed credit (a non-`active` from-state); a `redeemed`/`forfeited` credit cannot be redeemed.
    $credit = ClubCredit::factory()->create([
        'state' => ClubCreditState::Redeemed,
        'remaining' => Money::of(0, Currency::EUR),
    ]);

    // currency matches, but guard 1 (from-state) fires first — proving its precedence.
    expect(fn () => app(ApplyClubCredit::class)->handle($credit->id, Money::of(1000, Currency::EUR)))
        ->toThrow(IllegalClubCreditTransition::class);

    // the from-state guard fired before any write — `state` and `remaining` unchanged.
    $read = ClubCredit::findOrFail($credit->id);
    expect($read->state)->toBe(ClubCreditState::Redeemed)
        ->and($read->remaining->minorUnits)->toBe(0);
});

it('rejects a redemption whose currency differs from the credit currency, leaving it unchanged', function () {
    $credit = ClubCredit::factory()->create([
        'amount' => Money::of(25000, Currency::EUR),
        'remaining' => Money::of(25000, Currency::EUR),
    ]);

    // redeem USD against an EUR credit → currency mismatch (there is no FX in Module K — design L6). Asserted by
    // exception CLASS — the localized message key lands in task 5.2.
    expect(fn () => app(ApplyClubCredit::class)->handle($credit->id, Money::of(1000, Currency::USD)))
        ->toThrow(ClubCreditRedemptionPrecondition::class);

    $read = ClubCredit::findOrFail($credit->id);
    expect($read->state)->toBe(ClubCreditState::Active)
        ->and($read->remaining->equals(Money::of(25000, Currency::EUR)))->toBeTrue();
});

it('rejects an over-application exceeding remaining, leaving it unchanged', function () {
    $credit = ClubCredit::factory()->create([
        'amount' => Money::of(25000, Currency::EUR),
        'remaining' => Money::of(25000, Currency::EUR),
    ]);

    // redeem 25001 against a 25000 `remaining` → over-application; no negative balance is representable (AC-K-J-18).
    expect(fn () => app(ApplyClubCredit::class)->handle($credit->id, Money::of(25001, Currency::EUR)))
        ->toThrow(ClubCreditRedemptionPrecondition::class);

    $read = ClubCredit::findOrFail($credit->id);
    expect($read->state)->toBe(ClubCreditState::Active)
        ->and($read->remaining->equals(Money::of(25000, Currency::EUR)))->toBeTrue();
});

it('rejects redemption while the owning Profile is suspended (the freeze), leaving it unchanged', function () {
    // the credit is `active` but its owning Profile is `Suspended` → the credit is FROZEN (AC-K-FSM-2a; § 10.1);
    // it becomes mutable again on restore (the restore round-trip is task 3.2).
    $profile = Profile::factory()->create(['state' => ProfileState::Suspended]);
    $credit = ClubCredit::factory()->create([
        'profile_id' => $profile->id,
        'amount' => Money::of(25000, Currency::EUR),
        'remaining' => Money::of(25000, Currency::EUR),
    ]);

    expect(fn () => app(ApplyClubCredit::class)->handle($credit->id, Money::of(9000, Currency::EUR)))
        ->toThrow(ClubCreditRedemptionPrecondition::class);

    $read = ClubCredit::findOrFail($credit->id);
    expect($read->state)->toBe(ClubCreditState::Active)
        ->and($read->remaining->equals(Money::of(25000, Currency::EUR)))->toBeTrue();
});
