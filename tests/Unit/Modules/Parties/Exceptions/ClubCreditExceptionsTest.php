<?php

use App\Modules\Parties\Enums\ClubCreditState;
use App\Modules\Parties\Exceptions\ClubCreditIssuancePrecondition;
use App\Modules\Parties\Exceptions\ClubCreditRedemptionPrecondition;
use App\Modules\Parties\Exceptions\ClubCreditRestorePrecondition;
use App\Modules\Parties\Exceptions\IllegalClubCreditTransition;
use App\Platform\Money\Currency;
use Tests\TestCase;

// Pins the Club Credit domain-rejection exceptions (change club-credit, task 5.2; design L1/L2/L4/L6/L7;
// party-registry — Requirements: Club Credit Issuance, Club Credit Redemption and Carry-Forward, Club
// Credit Forfeiture and Restoration; Module K PRD § 11). The four within-module writer Actions
// (Issue/Apply/Forfeit/Restore) throw these on a rejected call; here we assert each named factory builds
// the right class with a localized reason that NAMES its interpolated business token and leaks NO PII.
// There are two PII registers among the keys:
//   - the FSM from-state guards ({@see IllegalClubCreditTransition}) and the currency mismatch carry only
//     a business enum / ISO-4217 token (a :state or a currency code) — no digit, no '@';
//   - the issuance / over-application / freeze / restore preconditions interpolate an operator-facing
//     :club / :credit id (a digit, like the sibling :producer / :customer id references) — never the money
//     balance, which the factories never receive (a balance is structurally un-leakable). So a digit is
//     EXPECTED there; an '@' (an email) is not.
// Booting the app (TestCase, NO RefreshDatabase — no DB is touched) makes the translator available so __()
// resolves the lang/en/parties.php copy instead of echoing the key back. Sibling: StatusTransitionExceptionsTest.

uses(TestCase::class);

// For each :state factory the chosen from-state's token is ABSENT from its key's literal template, so the
// token's presence in the resolved message proves :state was interpolated — not that the copy merely spells a
// similar word. This helper asserts the digit-free message contract: non-empty, names the interpolated token,
// and leaks no PII — no digit (an id/phone/DOB would) and no '@' (an email would).
$assertStateRejection = function (RuntimeException $exception, string $expectedToken): void {
    expect($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain($expectedToken);
    expect(preg_match('/\d/', $exception->getMessage()))->toBe(0);
    expect($exception->getMessage())->not->toContain('@');
};

// The id-bearing preconditions DO carry a digit (the operator-facing :club / :credit id). This helper asserts
// the id is interpolated and no email leaks; the money balance cannot leak because the factory only ever
// receives the int id (structural guarantee — no Money argument exists).
$assertIdRejection = function (RuntimeException $exception, string $expectedId): void {
    expect($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain($expectedId);
    expect($exception->getMessage())->not->toContain('@');
};

// --- FSM from-state guards (IllegalClubCreditTransition) ---------------------------------------------------

it('rejects applying a Club Credit that is not active, naming the offending state PII-free', function () use ($assertStateRejection) {
    // `forfeited` is absent from the cannot_apply template ("only from active"), so its presence proves :state.
    $exception = IllegalClubCreditTransition::cannotApply(ClubCreditState::Forfeited);

    expect($exception)->toBeInstanceOf(IllegalClubCreditTransition::class);
    $assertStateRejection($exception, 'forfeited');
});

it('rejects forfeiting a Club Credit that is not active, naming the offending state PII-free', function () use ($assertStateRejection) {
    // `redeemed` is absent from the cannot_forfeit template ("only from active"), so its presence proves :state.
    $exception = IllegalClubCreditTransition::cannotForfeit(ClubCreditState::Redeemed);

    expect($exception)->toBeInstanceOf(IllegalClubCreditTransition::class);
    $assertStateRejection($exception, 'redeemed');
});

it('rejects restoring a Club Credit that is not redeemed, naming the offending state PII-free', function () use ($assertStateRejection) {
    // `active` is absent from the cannot_restore template ("only from redeemed"), so its presence proves :state.
    $exception = IllegalClubCreditTransition::cannotRestore(ClubCreditState::Active);

    expect($exception)->toBeInstanceOf(IllegalClubCreditTransition::class);
    $assertStateRejection($exception, 'active');
});

// --- Redemption currency mismatch (ClubCreditRedemptionPrecondition::currencyMismatch) --------------------

it('rejects a cross-currency redemption, naming both ISO currency codes PII-free', function () {
    // :expected = credit currency (EUR), :actual = redeemed currency (USD) — both absent from the template,
    // so their presence proves both placeholders were interpolated. Currency codes carry no digit.
    $exception = ClubCreditRedemptionPrecondition::currencyMismatch(Currency::EUR, Currency::USD);

    expect($exception)->toBeInstanceOf(ClubCreditRedemptionPrecondition::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('EUR')
        ->and($exception->getMessage())->toContain('USD');
    expect(preg_match('/\d/', $exception->getMessage()))->toBe(0);
    expect($exception->getMessage())->not->toContain('@');
});

// --- ID-bearing preconditions (issuance / over-application / freeze / restore conflict) -------------------

it('rejects issuing on a Club with no credit policy, naming the club id without an email', function () use ($assertIdRejection) {
    $exception = ClubCreditIssuancePrecondition::clubDoesNotGenerateCredit(4242);

    expect($exception)->toBeInstanceOf(ClubCreditIssuancePrecondition::class);
    $assertIdRejection($exception, '4242');
});

it('rejects issuing on a Club with no fee, naming the club id without an email', function () use ($assertIdRejection) {
    $exception = ClubCreditIssuancePrecondition::clubHasNoFee(4242);

    expect($exception)->toBeInstanceOf(ClubCreditIssuancePrecondition::class);
    $assertIdRejection($exception, '4242');
});

it('rejects over-applying a Club Credit, naming the credit id without an email', function () use ($assertIdRejection) {
    $exception = ClubCreditRedemptionPrecondition::overApplication(909);

    expect($exception)->toBeInstanceOf(ClubCreditRedemptionPrecondition::class);
    $assertIdRejection($exception, '909');
});

it('rejects redeeming a frozen (suspended-Profile) Club Credit, naming the credit id without an email', function () use ($assertIdRejection) {
    $exception = ClubCreditRedemptionPrecondition::frozenWhileSuspended(909);

    expect($exception)->toBeInstanceOf(ClubCreditRedemptionPrecondition::class);
    $assertIdRejection($exception, '909');
});

it('rejects restoring into a one-active conflict, naming the credit id without an email', function () use ($assertIdRejection) {
    $exception = ClubCreditRestorePrecondition::profileHasActiveCredit(909);

    expect($exception)->toBeInstanceOf(ClubCreditRestorePrecondition::class);
    $assertIdRejection($exception, '909');
});

// --- Lang-key resolution (the i18n acceptance: every key resolves, placeholders wired) -------------------

it('resolves every Club Credit from-state lang key with the :state placeholder wired', function (string $key) {
    // 'retired' is a Producer-status token, deliberately ABSENT from every club_credit template, so its
    // presence proves :state was interpolated; a missing key would make Laravel echo the key back.
    $resolved = __($key, ['state' => 'retired']);

    expect($resolved)->not->toBe($key)
        ->and($resolved)->toContain('retired');
})->with([
    'parties.club_credit.cannot_apply',
    'parties.club_credit.cannot_forfeit',
    'parties.club_credit.cannot_restore',
]);

it('resolves the currency-mismatch lang key with both currency placeholders wired', function () {
    // CHF / JPY are absent from the template, so their presence proves both placeholders were interpolated.
    $resolved = __('parties.club_credit.currency_mismatch', ['expected' => 'CHF', 'actual' => 'JPY']);

    expect($resolved)->not->toBe('parties.club_credit.currency_mismatch')
        ->and($resolved)->toContain('CHF')
        ->and($resolved)->toContain('JPY');
});

it('resolves every id-bearing Club Credit lang key with its id placeholder wired', function (string $key, string $placeholder) {
    // The id (7777) is absent from every template, so its presence proves the :club / :credit placeholder
    // was interpolated; a missing key would make Laravel echo the key back.
    $resolved = __($key, [$placeholder => 7777]);

    expect($resolved)->not->toBe($key)
        ->and($resolved)->toContain('7777');
})->with([
    ['parties.club_credit.issuance_no_credit_policy', 'club'],
    ['parties.club_credit.issuance_no_fee', 'club'],
    ['parties.club_credit.over_application', 'credit'],
    ['parties.club_credit.frozen_while_suspended', 'credit'],
    ['parties.club_credit.restore_active_conflict', 'credit'],
]);

it('preserves the pre-existing parties lang groups', function () {
    // The club_credit group is ADDED alongside the parties-core / lifecycle / compliance / membership /
    // hold groups — not a rewrite; the pre-existing keys must still resolve (acceptance: existing groups
    // preserved). 'retired' is absent from these templates, so its presence proves :state interpolated.
    expect(__('parties.producer.cannot_activate', ['state' => 'retired']))
        ->not->toBe('parties.producer.cannot_activate')
        ->toContain('retired');

    expect(__('parties.club.cannot_close', ['state' => 'retired']))
        ->not->toBe('parties.club.cannot_close')
        ->toContain('retired');

    expect(__('parties.hold.cannot_lift_not_active', ['state' => 'lifted']))
        ->not->toBe('parties.hold.cannot_lift_not_active')
        ->toContain('lifted');

    expect(__('parties.customer.duplicate_email'))
        ->not->toBe('parties.customer.duplicate_email');

    expect(__('parties.profile.duplicate_for_club', ['customer' => 1, 'club' => 2]))
        ->not->toBe('parties.profile.duplicate_for_club');
});
