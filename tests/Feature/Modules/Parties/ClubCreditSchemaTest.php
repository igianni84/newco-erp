<?php

use App\Modules\Parties\Enums\ClubCreditState;
use App\Modules\Parties\Models\Profile;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Task 1.2 (change club-credit, design L1) — the `parties_club_credits` migration stands up the Club Credit
 * entity and the ONE-ACTIVE-CREDIT-PER-PROFILE structural invariant (the partial unique index
 * `(profile_id) WHERE state = 'active'`). These guards prove the schema at the RAW DB layer (the model + factory
 * land in task 1.3): columns present, the partial index enforced on BOTH engines, the FK + the NOT-NULL floors.
 * `down()` is the structural `Schema::dropIfExists` every sibling migration uses (it drops the table and, with
 * it, the partial index) — not separately tested, matching the parties_* migration convention. SQLite here; the
 * cross-engine close re-runs the suite on PostgreSQL 17 (tests-pgsql lane).
 */

/**
 * A complete, DB-layer-valid `parties_club_credits` row for the given parent profile id. Every column is NOT
 * NULL (no defaults — the IssueClubCredit Action is the sole writer, design L4), so the minimal row sets them
 * all; overrides drop/change one field per test. Amount mirrors the ClubFactory default fee (25000 EUR minor).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function clubCreditRow(int $profileId, array $overrides = []): array
{
    return array_merge([
        'profile_id' => $profileId,
        'amount_minor' => 25000,
        'amount_currency' => 'EUR',
        'remaining_minor' => 25000,
        'remaining_currency' => 'EUR',
        'valid_from' => now(),
        'valid_to' => now()->endOfYear(),
        'state' => ClubCreditState::Active->value,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}

it('creates parties_club_credits with the full entity columns', function () {
    expect(Schema::hasColumns('parties_club_credits', [
        'id', 'profile_id',
        'amount_minor', 'amount_currency', 'remaining_minor', 'remaining_currency',
        'valid_from', 'valid_to', 'state', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('has the one-active partial unique index, asserted by name (portable across engines)', function () {
    // Schema::getIndexes() surfaces an index's name + columns on BOTH SQLite and PostgreSQL but NOT its partial
    // predicate, so the portable proof of existence is the index NAME. The predicate `WHERE state = 'active'`
    // lives in the migration's raw DDL and runs identically on both engines — an invalid predicate would abort
    // the migration and red every test in this file, so a green suite also proves the partial DDL is valid SQL
    // on whichever engine is running (the tests-pgsql lane exercises the PostgreSQL side).
    expect(Schema::hasIndex('parties_club_credits', 'parties_club_credits_one_active_per_profile'))->toBeTrue();
});

it('accepts a fully-formed active credit row', function () {
    $profileId = Profile::factory()->create()->id;

    DB::table('parties_club_credits')->insert(clubCreditRow($profileId));

    expect(DB::table('parties_club_credits')->where('profile_id', $profileId)->value('state'))
        ->toBe(ClubCreditState::Active->value);
});

it('rejects a second active credit for the same profile via the partial unique index (both engines)', function () {
    $profileId = Profile::factory()->create()->id;

    DB::table('parties_club_credits')->insert(clubCreditRow($profileId));

    // A second ACTIVE credit for the same profile violates the partial unique index — the structural backstop on
    // BOTH engines (the index is created unconditionally, unlike the PG-only state CHECK). Wrapped in
    // DB::transaction (a SAVEPOINT under RefreshDatabase's wrapper) so PostgreSQL's transaction-abort stays
    // isolated and the count check after the throw is valid (the parties_profiles partial-index test idiom).
    expect(fn () => DB::transaction(fn () => DB::table('parties_club_credits')->insert(clubCreditRow($profileId))))
        ->toThrow(QueryException::class);

    expect(DB::table('parties_club_credits')->where('profile_id', $profileId)->count())->toBe(1);
});

it('lets redeemed and forfeited credits coexist with one active credit for the same profile (slot frees on exit)', function () {
    $profileId = Profile::factory()->create()->id;

    // A redeemed and a forfeited credit leave the `WHERE state = 'active'` predicate, so they do NOT occupy the
    // one-active slot — exactly one active credit coexists with any number of terminal ones. This is the freed
    // slot the next issuance fills after K.17 redemption or § 11.3 forfeiture.
    DB::table('parties_club_credits')->insert([
        clubCreditRow($profileId, ['state' => ClubCreditState::Redeemed->value, 'remaining_minor' => 0]),
        clubCreditRow($profileId, ['state' => ClubCreditState::Forfeited->value]),
        clubCreditRow($profileId, ['state' => ClubCreditState::Active->value]),
    ]);

    expect(DB::table('parties_club_credits')->where('profile_id', $profileId)->count())->toBe(3)
        ->and(DB::table('parties_club_credits')->where('profile_id', $profileId)
            ->where('state', ClubCreditState::Active->value)->count())->toBe(1);
});

it('rejects a credit whose profile_id has no parent profile (FK)', function () {
    // 999999 is not a parties_profiles.id — the within-module FK rejects the orphan. SQLite enforces FKs in this
    // app (foreign_key_constraints on), so this throws on both engines.
    DB::table('parties_club_credits')->insert(clubCreditRow(999999));
})->throws(QueryException::class);

it('rejects an insert that omits state — the column carries no default (NOT NULL floor)', function () {
    // state has NO default (design L4 — the Action sets it explicitly); a missing state is a NOT-NULL violation,
    // never a silent born-state default. This pins the deliberate no-default choice.
    $profileId = Profile::factory()->create()->id;
    $row = clubCreditRow($profileId);
    unset($row['state']);

    DB::table('parties_club_credits')->insert($row);
})->throws(QueryException::class);
