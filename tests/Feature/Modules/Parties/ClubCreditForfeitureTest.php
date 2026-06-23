<?php

use App\Modules\Parties\Actions\ApplyClubCredit;
use App\Modules\Parties\Actions\ForfeitClubCredit;
use App\Modules\Parties\Actions\IssueClubCredit;
use App\Modules\Parties\Actions\RestoreClubCredit;
use App\Modules\Parties\Enums\ClubCreditState;
use App\Modules\Parties\Exceptions\ClubCreditRestorePrecondition;
use App\Modules\Parties\Exceptions\IllegalClubCreditTransition;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\ClubCredit;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\DomainEvent;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;
use Illuminate\Database\QueryException;
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
 * Task 4.3 completes this file's matrix with the three cases the 4.1/4.2 set did not yet cover:
 *   - FORFEIT-BEFORE-ISSUE ordering (§ 11.3; design L5): with an `active` credit, a re-issue is rejected by the
 *     one-active partial index ({@see IssueClubCredit}), then `ForfeitClubCredit` frees the slot and a re-issue mints
 *     a FRESH `active` credit — the exact ordering the Module-E renewal listener will perform (forfeit-then-issue);
 *   - the RESTORE-AFTER-FORFEIT terminal edge (§ 11.3): a restore on a `forfeited` credit is rejected — the THIRD and
 *     last terminal edge (a second forfeit and an apply are exercised above), so `forfeited` is absolutely terminal;
 *   - the § 11.4 AUDIT-ONLY guarantee: forfeiture AND restoration record no `domain_events` row (delta 0 across both
 *     writers), Module K recording state with the lifecycle events owned by Module E.
 * RefreshDatabase per the directory convention; forfeiture touches only the credit while restoration re-reads the
 * owning Profile (the one-active check), neither asserting a clock-sensitive validity window (the forfeit-before-issue
 * case calls {@see IssueClubCredit}, which sets the window from the live clock, but asserts only `state`/identity, not
 * the window — so no frozen clock is needed). The factories bypass the Actions, so each credit is a pure fixture;
 * Money is asserted via `Money::equals()` / the integer `minorUnits`, never a float (invariant 6). Each Action opens
 * its OWN `DB::transaction` (a SAVEPOINT under RefreshDatabase's wrapper) — so the re-issue's partial-index violation
 * aborts only that SAVEPOINT and the file holds on PostgreSQL 17 as well as SQLite.
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

it('allows re-issue only after forfeiture — the forfeit-before-issue ordering (one-active invariant; design L5)', function () {
    // a credit-generating Club (factory default: generates_credit = true, fee = 25000 EUR) and a Profile on it — the
    // issuance-test idiom, so the REAL IssueClubCredit can mint a credit (it gates on generates_credit + a non-null fee).
    $club = Club::factory()->create();
    $profile = Profile::factory()->create(['club_id' => $club->id]);

    // issue the Profile's one `active` credit through the REAL Action.
    $first = app(IssueClubCredit::class)->handle($profile->id);
    expect($first->state)->toBe(ClubCreditState::Active);

    // a re-issue WHILE that credit is `active` violates the partial unique index `(profile_id) WHERE state = 'active'`
    // (design L1): the one-active invariant rejects a second active credit — the renewal flow cannot simply stack one.
    // IssueClubCredit wraps its work in DB::transaction, so the violation aborts only that SAVEPOINT and PostgreSQL
    // stays isolated under RefreshDatabase's outer transaction (the issuance-test one-active idiom).
    expect(fn () => app(IssueClubCredit::class)->handle($profile->id))
        ->toThrow(QueryException::class);
    expect(ClubCredit::query()->where('profile_id', $profile->id)
        ->where('state', ClubCreditState::Active->value)->count())->toBe(1);

    // forfeit the active credit (the forfeit-before-issue step — design L5): the slot frees, and a re-issue now mints
    // a FRESH `active` credit, leaving the original `forfeited` (outside the index). This is the exact ordering the
    // Module-E renewal listener will perform (ForfeitClubCredit then IssueClubCredit), provable at launch without it.
    app(ForfeitClubCredit::class)->handle($first->id);
    $second = app(IssueClubCredit::class)->handle($profile->id);

    expect($second->state)->toBe(ClubCreditState::Active)
        ->and($second->id)->not->toBe($first->id)
        ->and(ClubCredit::findOrFail($first->id)->state)->toBe(ClubCreditState::Forfeited)   // the original is terminal
        ->and(Profile::findOrFail($profile->id)->activeClubCredit?->is($second))->toBeTrue() // the new one is the active
        ->and(ClubCredit::query()->where('profile_id', $profile->id)->count())->toBe(2)
        ->and(ClubCredit::query()->where('profile_id', $profile->id)
            ->where('state', ClubCreditState::Active->value)->count())->toBe(1);
});

it('treats forfeited as terminal — a restore on a forfeited credit is rejected, leaving it forfeited', function () {
    // forfeit an `active` credit through the REAL Action, then attempt to RESTORE it: RestoreClubCredit's from-state
    // guard requires `redeemed`, so a `forfeited` credit is rejected via `cannotRestore` — the THIRD and last terminal
    // edge (a second forfeit and an apply are the other two, above). Forfeiture is absolutely terminal: no edge leaves it.
    $credit = ClubCredit::factory()->create([
        'amount' => Money::of(25000, Currency::EUR),
        'remaining' => Money::of(25000, Currency::EUR),
    ]);

    app(ForfeitClubCredit::class)->handle($credit->id);

    expect(fn () => app(RestoreClubCredit::class)->handle($credit->id))
        ->toThrow(IllegalClubCreditTransition::class);

    // the from-state guard fired before any write — `state` stays `forfeited` and `remaining` is untouched.
    $read = ClubCredit::findOrFail($credit->id);
    expect($read->state)->toBe(ClubCreditState::Forfeited)
        ->and($read->remaining->equals(Money::of(25000, Currency::EUR)))->toBeTrue();
});

it('records no domain event — forfeiture and restoration are audit-only (§ 11.4; design L3)', function () {
    // an `active` credit to forfeit and a fully-redeemed credit to restore — each on its OWN Profile (the factory
    // default spins up a fresh Profile per credit), so the redeemed one's one-active slot is free for the restore.
    // These are the two writers this file owns.
    $active = ClubCredit::factory()->create([
        'amount' => Money::of(25000, Currency::EUR),
        'remaining' => Money::of(25000, Currency::EUR),
    ]);
    $redeemed = ClubCredit::factory()->create([
        'amount' => Money::of(25000, Currency::EUR),
        'remaining' => Money::of(0, Currency::EUR),
        'state' => ClubCreditState::Redeemed,
    ]);

    // the factories bypass the Actions, so the event log starts empty; snapshot to assert the DELTA across BOTH
    // writers is exactly 0 (honest if a fixture ever emits), not merely a final count.
    $before = DomainEvent::query()->count();

    $forfeited = app(ForfeitClubCredit::class)->handle($active->id);
    $restored = app(RestoreClubCredit::class)->handle($redeemed->id);
    expect($forfeited->state)->toBe(ClubCreditState::Forfeited)   // the writers actually ran
        ->and($restored->state)->toBe(ClubCreditState::Active);

    // § 11.4 makes `ClubCreditForfeited` / `ClubCreditRestored` MODULE E's events; these within-module writers record
    // NONE — they inject no DomainEventRecorder (mirrors IssueClubCredit/ApplyClubCredit). Delta = 0.
    expect(DomainEvent::query()->count())->toBe($before);
});
