<?php

use App\Modules\Parties\Actions\ApplyClubCredit;
use App\Modules\Parties\Actions\ForfeitClubCredit;
use App\Modules\Parties\Actions\RestoreClubCredit;
use App\Modules\Parties\Enums\ClubCreditState;
use App\Modules\Parties\Exceptions\ClubCreditRestorePrecondition;
use App\Modules\Parties\Exceptions\IllegalClubCreditTransition;
use App\Modules\Parties\Models\ClubCredit;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the {@see ForfeitClubCredit} (change club-credit task 4.1) and {@see RestoreClubCredit} (task 4.2) Actions
 * (design L3/L4/L5/L7; party-registry — Requirement: Club Credit Forfeiture and Restoration; Module K PRD § 11.3 /
 * § 11.4). It drives the REAL Actions and asserts their acceptance bullets:
 *   - FORFEITURE (task 4.1): the `active → forfeited` happy path — the sole writer of the forfeiture edge, leaving
 *     `remaining` intact (the residual balance is the Module-S DEC-043 conversion input, not zeroed by Module K); the
 *     FSM from-state guard rejecting a non-`active` credit BEFORE any write → {@see IllegalClubCreditTransition} (via
 *     `cannotForfeit`); and `forfeited` ABSOLUTELY TERMINAL (§ 11.3 — at most one forfeiture per lifetime): a second
 *     forfeit and an apply on a forfeited credit are both rejected by their respective from-state guards.
 *   - RESTORATION (task 4.2): the `redeemed → active` happy path — the sole writer of the restore edge, returning the
 *     credit to `active` with its `remaining` restored to the full face value (a `redeemed` credit was fully spent —
 *     `remaining` 0 — so restoration re-opens `remaining = amount`, design L7 / § 11.2); the FSM from-state guard
 *     rejecting a non-`redeemed` credit → {@see IllegalClubCreditTransition} (via `cannotRestore`); and the
 *     one-active-per-Profile precondition rejecting a restore when the Profile already holds another `active` credit
 *     → {@see ClubCreditRestorePrecondition} (the partial index is respected, not violated — design L1/L7).
 *
 * Rejections are asserted by exception CLASS, not message — the localized `parties.club_credit.*` keys land in task
 * 5.2, so until then `__()` returns the key string and the class is the pinned contract (the redemption-test idiom).
 * Task 4.3 completes this file's matrix with the forfeit-before-issue ordering pair (the one-active invariant makes
 * `IssueClubCredit` reject while `active`, so re-issue requires forfeit-then-issue), the restore-after-forfeit
 * terminal edge (restore on a forfeited credit is rejected), and the § 11.4 audit-only guarantee (no `domain_events`
 * row — delta 0 — across the writers). RefreshDatabase per the directory convention; forfeiture touches only the
 * credit while restoration re-reads the owning Profile (the one-active check), neither asserting a clock-sensitive
 * validity window. The factories bypass the Actions, so each credit is a pure fixture; Money is asserted via
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

it('restores a redeemed credit, transitioning it to active and restoring its full remaining', function () {
    // a fully-redeemed credit — the ONLY way to reach `redeemed` is a full spend, so `remaining` is 0 (ApplyClubCredit
    // sets `redeemed` exactly when the balance hits zero) — whose Profile holds no other `active` credit.
    $credit = ClubCredit::factory()->create([
        'amount' => Money::of(25000, Currency::EUR),
        'remaining' => Money::of(0, Currency::EUR),
        'state' => ClubCreditState::Redeemed,
    ]);

    $restored = app(RestoreClubCredit::class)->handle($credit->id);

    // the credit returns to `active` with its `remaining` restored to the full face value (design L7; § 11.2 — the
    // order-cancellation reversal re-opens the fully-spent balance: `remaining = amount`).
    expect($restored->state)->toBe(ClubCreditState::Active)
        ->and($restored->remaining->equals(Money::of(25000, Currency::EUR)))->toBeTrue();

    // re-fetch so the assertion exercises the persisted record (the MoneyCast round-trip), not the in-memory model.
    $read = ClubCredit::findOrFail($credit->id);
    expect($read->state)->toBe(ClubCreditState::Active)
        ->and($read->remaining->equals(Money::of(25000, Currency::EUR)))->toBeTrue()
        ->and($read->amount->equals(Money::of(25000, Currency::EUR)))->toBeTrue();   // `amount` is untouched
});

it('rejects restoration of a non-redeemed credit (the FSM from-state guard), leaving it unchanged', function () {
    // an `active` credit — restoration departs only from `redeemed`; an `active` credit needs no restoration, so the
    // from-state guard rejects it.
    $credit = ClubCredit::factory()->create([
        'amount' => Money::of(25000, Currency::EUR),
        'remaining' => Money::of(25000, Currency::EUR),
    ]);

    expect(fn () => app(RestoreClubCredit::class)->handle($credit->id))
        ->toThrow(IllegalClubCreditTransition::class);

    // the from-state guard fired before any write — `state` and `remaining` unchanged.
    $read = ClubCredit::findOrFail($credit->id);
    expect($read->state)->toBe(ClubCreditState::Active)
        ->and($read->remaining->equals(Money::of(25000, Currency::EUR)))->toBeTrue();
});

it('rejects restoration when the Profile already holds another active credit (the one-active invariant is preserved)', function () {
    // a `redeemed` credit, then a SECOND `active` credit on the SAME Profile (the renewal-replacement case). The
    // redeemed credit is created FIRST: being outside the `active` scope it leaves the one-active slot free, so the
    // active replacement inserts cleanly under the partial index (the same ordering as the 1.4 coexistence fixture).
    $redeemed = ClubCredit::factory()->create([
        'amount' => Money::of(25000, Currency::EUR),
        'remaining' => Money::of(0, Currency::EUR),
        'state' => ClubCreditState::Redeemed,
    ]);
    ClubCredit::factory()->create([
        'profile_id' => $redeemed->profile_id,
        'amount' => Money::of(25000, Currency::EUR),
        'remaining' => Money::of(25000, Currency::EUR),
    ]);

    // restoring the redeemed credit now would breach the one-active invariant, so it is refused with the precondition
    // (not left to abort on the partial unique index — design L1/L7).
    expect(fn () => app(RestoreClubCredit::class)->handle($redeemed->id))
        ->toThrow(ClubCreditRestorePrecondition::class);

    // the precondition fired before any write — the redeemed credit stays `redeemed` with `remaining` 0.
    $read = ClubCredit::findOrFail($redeemed->id);
    expect($read->state)->toBe(ClubCreditState::Redeemed)
        ->and($read->remaining->minorUnits)->toBe(0);
});
