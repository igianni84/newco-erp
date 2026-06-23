<?php

use App\Modules\Parties\Actions\ApplyClubCredit;
use App\Modules\Parties\Actions\ForfeitClubCredit;
use App\Modules\Parties\Enums\ClubCreditState;
use App\Modules\Parties\Exceptions\IllegalClubCreditTransition;
use App\Modules\Parties\Models\ClubCredit;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the {@see ForfeitClubCredit} Action (change club-credit task 4.1; design L3/L4/L5; party-registry —
 * Requirement: Club Credit Forfeiture and Restoration; Module K PRD § 11.3 / § 11.4). It drives the REAL Action and
 * asserts the acceptance bullets of the forfeiture writer:
 *   - the `active → forfeited` happy path: the sole writer of the forfeiture edge, leaving `remaining` intact (the
 *     residual balance is the Module-S DEC-043 conversion input, not zeroed by Module K);
 *   - the FSM from-state guard, rejecting BEFORE any write so `state`/`remaining` are left unchanged: a non-`active`
 *     credit → {@see IllegalClubCreditTransition} (via `cannotForfeit`);
 *   - `forfeited` is ABSOLUTELY TERMINAL (§ 11.3 — at most one forfeiture per lifetime): a second forfeit and an
 *     apply on a forfeited credit are both rejected by their respective from-state guards.
 *
 * Rejections are asserted by exception CLASS, not message — the localized `parties.club_credit.*` keys land in task
 * 5.2, so until then `__()` returns the key string and the class is the pinned contract (the redemption-test idiom).
 * Task 4.3 completes this file's matrix with the forfeit-before-issue ordering pair (the one-active invariant makes
 * `IssueClubCredit` reject while `active`, so re-issue requires forfeit-then-issue), the restore cases (once
 * `RestoreClubCredit` ships in task 4.2 — including restore-after-forfeit, the third terminal edge), and the
 * § 11.4 audit-only guarantee (no `domain_events` row — delta 0). RefreshDatabase per the
 * directory convention; forfeiture touches only the credit (no Profile re-read, no clock-sensitive validity window
 * asserted). The factories bypass the Actions, so each credit is a pure fixture; Money is asserted via
 * `Money::equals()` / the integer `minorUnits`, never a float (invariant 6). Each Action opens its OWN
 * `DB::transaction` (a SAVEPOINT under RefreshDatabase's wrapper), so the file holds on PostgreSQL 17 as well as SQLite.
 */
uses(RefreshDatabase::class);

it('forfeits an active credit, transitioning it to forfeited and leaving remaining intact', function () {
    // an `active` credit with `remaining` 25000 EUR.
    $credit = ClubCredit::factory()->create([
        'amount' => Money::of(25000, Currency::EUR),
        'remaining' => Money::of(25000, Currency::EUR),
    ]);

    $forfeited = app(ForfeitClubCredit::class)->handle($credit->id);

    // the credit transitions `active → forfeited`; `remaining` is left intact (forfeiture is a state change — the
    // residual balance is the Module-S DEC-043 conversion input, not zeroed by Module K).
    expect($forfeited->state)->toBe(ClubCreditState::Forfeited)
        ->and($forfeited->remaining->equals(Money::of(25000, Currency::EUR)))->toBeTrue();

    // re-fetch so the assertion exercises the persisted record (the MoneyCast round-trip), not the in-memory model.
    $read = ClubCredit::findOrFail($credit->id);
    expect($read->state)->toBe(ClubCreditState::Forfeited)
        ->and($read->remaining->equals(Money::of(25000, Currency::EUR)))->toBeTrue()
        ->and($read->amount->equals(Money::of(25000, Currency::EUR)))->toBeTrue();   // `amount` is untouched too
});

it('rejects forfeiture of a non-active credit (the FSM from-state guard), leaving it unchanged', function () {
    // a fully-redeemed credit (a non-`active` from-state); a `redeemed` credit cannot be forfeited (the enum nuance —
    // `redeemed` is restore-reachable, not forfeitable).
    $credit = ClubCredit::factory()->create([
        'state' => ClubCreditState::Redeemed,
        'remaining' => Money::of(0, Currency::EUR),
    ]);

    expect(fn () => app(ForfeitClubCredit::class)->handle($credit->id))
        ->toThrow(IllegalClubCreditTransition::class);

    // the from-state guard fired before any write — `state` and `remaining` unchanged.
    $read = ClubCredit::findOrFail($credit->id);
    expect($read->state)->toBe(ClubCreditState::Redeemed)
        ->and($read->remaining->minorUnits)->toBe(0);
});

it('treats forfeited as terminal — a second forfeit is rejected, leaving it forfeited', function () {
    // forfeit an `active` credit through the REAL Action, then attempt to forfeit it AGAIN: the same from-state guard
    // rejects (§ 11.3 — at most one forfeiture per lifetime; `forfeited` is absolutely terminal).
    $credit = ClubCredit::factory()->create([
        'amount' => Money::of(25000, Currency::EUR),
        'remaining' => Money::of(25000, Currency::EUR),
    ]);

    app(ForfeitClubCredit::class)->handle($credit->id);

    expect(fn () => app(ForfeitClubCredit::class)->handle($credit->id))
        ->toThrow(IllegalClubCreditTransition::class);

    $read = ClubCredit::findOrFail($credit->id);
    expect($read->state)->toBe(ClubCreditState::Forfeited)
        ->and($read->remaining->equals(Money::of(25000, Currency::EUR)))->toBeTrue();
});

it('treats forfeited as terminal — an apply on a forfeited credit is rejected, leaving it forfeited', function () {
    // forfeit an `active` credit, then attempt to REDEEM it: ApplyClubCredit's own from-state guard (`cannotApply`)
    // rejects, proving forfeiture closes the redemption edge too (the second of the three terminal edges; restore
    // is the third, exercised in task 4.3 once RestoreClubCredit ships).
    $credit = ClubCredit::factory()->create([
        'amount' => Money::of(25000, Currency::EUR),
        'remaining' => Money::of(25000, Currency::EUR),
    ]);

    app(ForfeitClubCredit::class)->handle($credit->id);

    expect(fn () => app(ApplyClubCredit::class)->handle($credit->id, Money::of(9000, Currency::EUR)))
        ->toThrow(IllegalClubCreditTransition::class);

    $read = ClubCredit::findOrFail($credit->id);
    expect($read->state)->toBe(ClubCreditState::Forfeited)
        ->and($read->remaining->equals(Money::of(25000, Currency::EUR)))->toBeTrue();
});
