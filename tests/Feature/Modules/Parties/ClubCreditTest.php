<?php

use App\Modules\Parties\Enums\ClubCreditState;
use App\Modules\Parties\Models\ClubCredit;
use App\Modules\Parties\Models\Profile;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Pins the ClubCredit model + factory (change club-credit task 1.3; design L1/L4) — the per-Profile prepayment
 * instrument the membership fee converts into (Module K PRD § 11). It proves the persistence-only model hydrates
 * its two Money fields through the MoneyCast with no precision loss (integer minor units + an ISO 4217 code,
 * NEVER a float — invariant 6), casts `state` to {@see ClubCreditState}, normalizes the validity window through
 * the `immutable_datetime` cast, resolves the within-module {@see ClubCredit::profile()} `belongsTo`, and — the
 * acceptance's model-layer re-assertion of the schema invariant — that the one-active partial unique index holds
 * THROUGH the Eloquent write path (a second `active` credit for one Profile is rejected; a terminal credit frees
 * the slot). The raw schema-layer proof is the sibling ClubCreditSchemaTest (task 1.2); this is the Eloquent
 * counterpart, not a duplicate.
 *
 * RefreshDatabase: the one-active probe is savepoint-wrapped (DB::transaction, testing-rule #5) so PostgreSQL's
 * transaction-abort on the unique violation stays isolated and the count check after the throw is valid on both
 * engines. The Money is read back THROUGH the cast and asserted by Money::equals() and at the raw column level
 * (the ClubTest idiom) — never a byte-compare.
 */
uses(RefreshDatabase::class);

it('round-trips a Club Credit through the factory with the Money fields, state enum and validity window intact', function () {
    $profile = Profile::factory()->create();

    $credit = ClubCredit::factory()->create(['profile_id' => $profile->id]);

    // Re-fetch so the assertions exercise the read/hydration casts, not the in-memory create() values.
    $read = ClubCredit::findOrFail($credit->id);

    expect($read->profile_id)->toBe($profile->id)
        ->and($read->state)->toBe(ClubCreditState::Active)                            // state casts to the enum
        ->and($read->amount)->toBeInstanceOf(Money::class)
        ->and($read->amount->equals(Money::of(25000, Currency::EUR)))->toBeTrue()     // Money round-trips by value
        ->and($read->remaining)->toBeInstanceOf(Money::class)
        ->and($read->remaining->equals($read->amount))->toBeTrue()                    // remaining = amount (untouched)
        ->and($read->valid_from)->toBeInstanceOf(CarbonImmutable::class)              // immutable_datetime cast
        ->and($read->valid_to)->toBeInstanceOf(CarbonImmutable::class)
        ->and($read->valid_to->format('Y-m-d'))->toBe(CarbonImmutable::now()->endOfYear()->format('Y-m-d')); // 31 Dec of issuance year

    // The amount/remaining are integer + string columns on disk, NEVER a float (invariant 6). Read at the raw
    // column level via the MoneyCast `{key}_minor`/`{key}_currency` convention with ->value() (the ClubTest
    // idiom); toEqual tolerates drivers that return integer columns as numeric strings.
    expect(DB::table('parties_club_credits')->where('id', $credit->id)->value('amount_minor'))->toEqual(25000)
        ->and(DB::table('parties_club_credits')->where('id', $credit->id)->value('amount_currency'))->toBe('EUR')
        ->and(DB::table('parties_club_credits')->where('id', $credit->id)->value('remaining_minor'))->toEqual(25000)
        ->and(DB::table('parties_club_credits')->where('id', $credit->id)->value('remaining_currency'))->toBe('EUR');

    // The within-module belongsTo resolves the owning Profile (relations are allowed within Module K).
    expect($read->profile->is($profile))->toBeTrue();
});

it('rejects a second active credit for the same Profile through the Eloquent path (one-active partial index)', function () {
    $credit = ClubCredit::factory()->create();

    // A second ACTIVE credit for the SAME Profile violates the partial unique index `(profile_id) WHERE
    // state = 'active'` — the structural one-active guard, exercised here through the Eloquent write path (the
    // factory + the MoneyCast/enum `set` legs), not raw DDL. Wrapped in DB::transaction (a SAVEPOINT under
    // RefreshDatabase's wrapper) so PostgreSQL's transaction-abort stays isolated and the count after the throw
    // is valid on both engines.
    expect(fn () => DB::transaction(
        fn () => ClubCredit::factory()->create(['profile_id' => $credit->profile_id])
    ))->toThrow(QueryException::class);

    expect(ClubCredit::query()->where('profile_id', $credit->profile_id)->count())->toBe(1);
});

it('frees the one-active slot when the credit leaves active, admitting a fresh active credit (Eloquent path)', function () {
    $credit = ClubCredit::factory()->create();

    // Moving the credit out of `active` (here straight to redeemed — the Apply Action lands in task 3) takes it
    // out of the `WHERE state = 'active'` predicate, so it no longer occupies the one-active slot. A fresh active
    // credit for the same Profile now inserts cleanly — the freed slot the next issuance fills (§ 11.2/§ 11.3).
    $credit->update(['state' => ClubCreditState::Redeemed]);

    $replacement = ClubCredit::factory()->create(['profile_id' => $credit->profile_id]);

    expect($replacement->state)->toBe(ClubCreditState::Active)
        ->and(ClubCredit::query()->where('profile_id', $credit->profile_id)->count())->toBe(2)
        ->and(ClubCredit::query()->where('profile_id', $credit->profile_id)
            ->where('state', ClubCreditState::Active->value)->count())->toBe(1);
});
