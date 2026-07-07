<?php

use App\Modules\Parties\Exceptions\BelowMinimumRegistrationAge;
use App\Modules\Parties\Exceptions\ClubNotAcceptingMemberships;
use App\Modules\Parties\Exceptions\ProducerAgreementClubNotActive;
use App\Modules\Parties\Exceptions\ProducerAgreementScopeConflict;
use App\Modules\Parties\Exceptions\ProducerReviewGovernedContentLocked;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

// Pins the Module-K business-rule guard exceptions of change parties-module-k-br-guards (task 2.4; design
// D2/D3/D5/D7/D9; party-registry — Requirements: ProducerAgreement Lifecycle, ProducerAgreement, Profile —
// Multi-Profile Membership, Registration Age Gate, Producer Review-Governed Content Lock; canon MVP-DEC-009 /
// MVP-DEC-022). Each is a localized RuntimeException subclass whose named factories the wiring tasks (3.2/3.3/
// 4.1/5.1/5.2) throw; here we assert each factory builds its class with a localized reason that NAMES its
// interpolated business token and leaks NO PII. The interpolated tokens are operator-facing references (a
// :producer / :club id — a digit — or a :state / :min_age business/config value), never customer data: the
// date of birth and the derived age are PII and are structurally un-leakable (the age-gate factories only ever
// receive the :min_age constant), the SoD / DuplicateCustomerEmail discipline. Booting the app (TestCase, NO
// RefreshDatabase — no DB is touched) makes the translator available so __()/trans() resolve lang/{en,it}/
// parties.php instead of echoing the key back. Sibling: SeparationOfDutiesViolationTest, ClubCreditExceptionsTest.

uses(TestCase::class);

// --- Base class (the operator console kit catches RuntimeException on its outcome path) --------------------

// `RuntimeException::class` is intentionally BARE (no `use`): a Pest file runs in the global namespace, so the
// token already resolves to \RuntimeException — importing it would be redundant. The base class is load-bearing
// (the OperatorPanel action verbs render an action_failed notification off it, as for the Catalog/SoD siblings).
it('are RuntimeExceptions so the operator console kit surfaces them via its outcome path', function () {
    expect(new ProducerAgreementScopeConflict(''))->toBeInstanceOf(RuntimeException::class);
    expect(new ProducerAgreementClubNotActive(''))->toBeInstanceOf(RuntimeException::class);
    expect(new ClubNotAcceptingMemberships(''))->toBeInstanceOf(RuntimeException::class);
    expect(new BelowMinimumRegistrationAge(''))->toBeInstanceOf(RuntimeException::class);
    expect(new ProducerReviewGovernedContentLocked(''))->toBeInstanceOf(RuntimeException::class);
});

// --- ProducerAgreementScopeConflict (RM-20, cross-shape mutual exclusion — task 3.3) ----------------------

it('builds both direction-aware scope conflicts, each naming the producer id, with distinct copy', function () {
    // 7788 is absent from both templates, so its presence proves :producer was interpolated. The two directions
    // (Producer-wide blocked by a per-Club active / per-Club blocked by a Producer-wide active) name which
    // existing shape to terminate first, so they MUST resolve distinct copy.
    $producerWide = ProducerAgreementScopeConflict::producerWideBlockedByClubScope(7788);
    $clubScope = ProducerAgreementScopeConflict::clubScopeBlockedByProducerWide(7788);

    expect($producerWide)->toBeInstanceOf(ProducerAgreementScopeConflict::class);
    expect($clubScope)->toBeInstanceOf(ProducerAgreementScopeConflict::class);

    foreach ([$producerWide, $clubScope] as $e) {
        expect($e->getMessage())->not->toBe('');
        expect($e->getMessage())->toContain('7788');    // the operator-facing :producer id
        expect($e->getMessage())->not->toContain('@');  // no email — the copy names only the rule
    }

    expect($producerWide->getMessage())->not->toBe($clubScope->getMessage());
});

// --- ProducerAgreementClubNotActive (Agreement-4, per-Club scope requires an active Club — task 3.2) -------

it('builds the ProducerAgreement Club-not-active rejection naming the club id and offending state', function () {
    // 5566 / sunset are both absent from the template (which says "not active"), so their presence proves the
    // :club and :state placeholders were interpolated.
    $exception = ProducerAgreementClubNotActive::forClub(5566, 'sunset');

    expect($exception)->toBeInstanceOf(ProducerAgreementClubNotActive::class);
    expect($exception->getMessage())->not->toBe('');
    expect($exception->getMessage())->toContain('5566');
    expect($exception->getMessage())->toContain('sunset');
    expect($exception->getMessage())->not->toContain('@');
});

// --- ClubNotAcceptingMemberships (RM-21, sunset/closed Club blocks new membership — task 4.1) --------------

it('builds the Club-not-accepting-memberships rejection naming the club id and offending state', function () {
    // 5566 / closed are absent from the template (which only spells "active"), so their presence proves the
    // :club and :state placeholders were interpolated.
    $exception = ClubNotAcceptingMemberships::forClub(5566, 'closed');

    expect($exception)->toBeInstanceOf(ClubNotAcceptingMemberships::class);
    expect($exception->getMessage())->not->toBe('');
    expect($exception->getMessage())->toContain('5566');
    expect($exception->getMessage())->toContain('closed');
    expect($exception->getMessage())->not->toContain('@');
});

// --- BelowMinimumRegistrationAge (Identity-6, 18+ age gate — task 5.1) -------------------------------------

it('builds both age-gate rejections naming only the min-age constant, leaking no date of birth', function () {
    // 99 is the (test) :min_age platform constant — a public config value, NOT PII — and is absent from both
    // templates, so its presence proves interpolation. The underage-DOB and missing-DOB modes are distinct
    // failures → distinct copy. No '@' (an email) and, structurally, no DOB/derived age can appear: the
    // factories receive only the constant.
    $below = BelowMinimumRegistrationAge::belowMinimum(99);
    $missing = BelowMinimumRegistrationAge::missingDateOfBirth(99);

    expect($below)->toBeInstanceOf(BelowMinimumRegistrationAge::class);
    expect($missing)->toBeInstanceOf(BelowMinimumRegistrationAge::class);

    foreach ([$below, $missing] as $e) {
        expect($e->getMessage())->not->toBe('');
        expect($e->getMessage())->toContain('99');
        expect($e->getMessage())->not->toContain('@');
    }

    expect($below->getMessage())->not->toBe($missing->getMessage());
});

// --- ProducerReviewGovernedContentLocked (Producer-5 interim, content lock while active — task 5.2) --------

it('builds the Producer content-lock rejection naming the producer id', function () {
    // 7788 is absent from the template, so its presence proves :producer was interpolated.
    $exception = ProducerReviewGovernedContentLocked::whileActive(7788);

    expect($exception)->toBeInstanceOf(ProducerReviewGovernedContentLocked::class);
    expect($exception->getMessage())->not->toBe('');
    expect($exception->getMessage())->toContain('7788');
    expect($exception->getMessage())->not->toContain('@');
});

// --- Lang-key resolution (the i18n acceptance: every key resolves EN + IT, placeholders wired) -------------
//
// Grouped by placeholder shape (the ClubCreditExceptionsTest idiom): each group builds its replacement array
// inline as a literal so it is exactly `trans()`'s `array<string, scalar>` contract. The replacement VALUES
// (7788 / 5566 / sunset / 99) are absent from every template, so their presence in the resolved English proves
// the placeholder was interpolated; a missing key would make Laravel echo the key back unchanged. The IT check
// mirrors PartiesApprovalCopyTest: genuinely authored in `it` (Lang::has third arg false = no fallback) AND
// distinct from the English value — proving Italian rendering, not the per-key EN fallback firing.

$producerIdKeys = [
    'parties.producer.review_governed_content_locked',
    'parties.producer_agreement.scope_conflict_producer_wide',
    'parties.producer_agreement.scope_conflict_club_scope',
];

it('resolves every :producer BR-guard key in en with the id interpolated', function (string $key) {
    $resolved = trans($key, ['producer' => 7788], 'en');

    expect($resolved)->not->toBe($key)
        ->and($resolved)->toContain('7788');
})->with($producerIdKeys);

it('renders every :producer BR-guard key in authored Italian under it, distinct from en', function (string $key) {
    App::setLocale('it');

    expect(Lang::has($key, 'it', false))->toBeTrue("expected {$key} to be authored in it")
        ->and(trans($key, ['producer' => 7788], 'it'))->not->toBe('')
        ->and(trans($key, ['producer' => 7788], 'it'))->not->toBe($key)
        ->and(trans($key, ['producer' => 7788], 'it'))->not->toBe(trans($key, ['producer' => 7788], 'en'));
})->with($producerIdKeys);

$clubStateKeys = [
    'parties.producer_agreement.club_not_active',
    'parties.club.not_accepting_memberships',
];

it('resolves every :club/:state BR-guard key in en with both placeholders interpolated', function (string $key) {
    $resolved = trans($key, ['club' => 5566, 'state' => 'sunset'], 'en');

    expect($resolved)->not->toBe($key)
        ->and($resolved)->toContain('5566')
        ->and($resolved)->toContain('sunset');
})->with($clubStateKeys);

it('renders every :club/:state BR-guard key in authored Italian under it, distinct from en', function (string $key) {
    App::setLocale('it');

    expect(Lang::has($key, 'it', false))->toBeTrue("expected {$key} to be authored in it")
        ->and(trans($key, ['club' => 5566, 'state' => 'sunset'], 'it'))->not->toBe('')
        ->and(trans($key, ['club' => 5566, 'state' => 'sunset'], 'it'))->not->toBe($key)
        ->and(trans($key, ['club' => 5566, 'state' => 'sunset'], 'it'))->not->toBe(trans($key, ['club' => 5566, 'state' => 'sunset'], 'en'));
})->with($clubStateKeys);

$minAgeKeys = [
    'parties.customer.below_minimum_registration_age',
    'parties.customer.missing_date_of_birth',
];

it('resolves every :min_age BR-guard key in en with the constant interpolated', function (string $key) {
    $resolved = trans($key, ['min_age' => 99], 'en');

    expect($resolved)->not->toBe($key)
        ->and($resolved)->toContain('99');
})->with($minAgeKeys);

it('renders every :min_age BR-guard key in authored Italian under it, distinct from en', function (string $key) {
    App::setLocale('it');

    expect(Lang::has($key, 'it', false))->toBeTrue("expected {$key} to be authored in it")
        ->and(trans($key, ['min_age' => 99], 'it'))->not->toBe('')
        ->and(trans($key, ['min_age' => 99], 'it'))->not->toBe($key)
        ->and(trans($key, ['min_age' => 99], 'it'))->not->toBe(trans($key, ['min_age' => 99], 'en'));
})->with($minAgeKeys);
