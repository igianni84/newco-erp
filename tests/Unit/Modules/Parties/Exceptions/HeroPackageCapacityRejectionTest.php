<?php

use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Exceptions\IllegalProfileTransition;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

// Pins IllegalProfileTransition::clubAtCapacity() — the ONE Hero-Package capacity rejection (change
// parties-hero-package, task 1.3; design D8; party-registry — Requirement: Hero Package Capacity Invariant;
// canon MVP-DEC-017 / Module_K_PRD § 13.1 / AC-K-J-13). Two seat-consuming transitions share it: `ApproveProfile`
// on an already-`waiting_list` Profile whose Club is still full, and `RenewProfile` on a `lapsed` Profile within
// its 30-day grace. Here we assert the factory builds the right class with a localized reason that interpolates
// all three placeholders and leaks no customer data; the Actions are wired to throw it in this change's later
// tasks (2.2 / 2.4). Booting the app (TestCase, NO RefreshDatabase — no DB is touched) makes the translator
// available so __()/trans() resolve lang/{en,it}/parties.php instead of echoing the key back.
// Siblings: MembershipTransitionExceptionsTest, StatusTransitionExceptionsTest, BrGuardExceptionsTest.

uses(TestCase::class);

// The three replacement values are chosen ABSENT from both templates, so their presence in the resolved message
// proves each placeholder was interpolated — not merely that the copy spells a similar word:
//   - `waiting_list` / `lapsed` appear in neither the EN nor the IT template
//   - 88 and 77 are digits, and the templates carry none
// 88 > 77 on purpose: occupancy ABOVE capacity is a real state (a capacity lowered beneath the sitting members —
// `ClubSeatOccupancy::wouldOversell()` compares with `>=`, not `>`), and distinct numbers prove the two cardinals
// are not transposed.

it('builds a localized capacity rejection naming the from-state, the occupancy and the capacity', function () {
    $exception = IllegalProfileTransition::clubAtCapacity(ProfileState::WaitingList, capacity: 77, occupiedSeats: 88);

    expect($exception)->toBeInstanceOf(IllegalProfileTransition::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('waiting_list')
        ->and($exception->getMessage())->toContain('88')
        ->and($exception->getMessage())->toContain('77');
});

// The base class is load-bearing: the operator console kit catches RuntimeException by base type on its outcome
// path (design L4 — it imports nothing from a module's Exceptions namespace) and renders the danger notification
// off the domain's localized message.
it('is a RuntimeException so the operator console kit surfaces it as a danger notification', function () {
    expect(IllegalProfileTransition::clubAtCapacity(ProfileState::Lapsed, capacity: 77, occupiedSeats: 88))
        ->toBeInstanceOf(RuntimeException::class);
});

// One factory, two rejecting call sites (design D8). `applied` is deliberately NOT in this dataset: at parity it is
// DIVERTED to `waiting_list`, never rejected — this factory is for the absence of an edge, not the absence of a seat.
it('serves both rejecting call sites, naming whichever from-state was refused', function (ProfileState $from) {
    $exception = IllegalProfileTransition::clubAtCapacity($from, capacity: 77, occupiedSeats: 88);

    expect($exception->getMessage())->toContain($from->value);
})->with([
    'approve of a still-waitlisted Profile' => ProfileState::WaitingList,
    'within-grace renewal of a lapsed Profile' => ProfileState::Lapsed,
]);

// The reason interpolates ONLY the from-state token and two Club-level seat cardinals. It must never name the
// customer behind the refused seat: no email, no name. The `\d` assertion of the PII-free siblings cannot apply
// here (the occupancy IS a number), so we assert the complement — the message carries exactly the two cardinals
// the gate decided on and no other digit run.
it('names the seat ledger and no customer, carrying exactly the two cardinals the gate decided on', function () {
    $message = IllegalProfileTransition::clubAtCapacity(ProfileState::WaitingList, capacity: 77, occupiedSeats: 88)
        ->getMessage();

    expect($message)->not->toContain('@');
    preg_match_all('/\d+/', $message, $matches);
    expect($matches[0])->toBe(['88', '77']);
});

it('resolves the capacity key in en with every placeholder interpolated', function () {
    $resolved = trans('parties.profile.club_at_capacity', ['state' => 'waiting_list', 'capacity' => 77, 'occupied' => 88], 'en');

    expect($resolved)->not->toBe('parties.profile.club_at_capacity')
        ->and($resolved)->toContain('waiting_list')
        ->and($resolved)->toContain('88')
        ->and($resolved)->toContain('77');
});

// Genuinely authored in `it` (Lang::has third arg false = no fallback) AND distinct from the English value — proves
// Italian rendering, not the per-key EN fallback firing (the PartiesApprovalCopyTest / BrGuardExceptionsTest idiom).
it('renders the capacity key in authored Italian under it, distinct from en', function () {
    App::setLocale('it');

    $replace = ['state' => 'waiting_list', 'capacity' => 77, 'occupied' => 88];

    expect(Lang::has('parties.profile.club_at_capacity', 'it', false))->toBeTrue()
        ->and(trans('parties.profile.club_at_capacity', $replace, 'it'))->not->toBe('')
        ->and(trans('parties.profile.club_at_capacity', $replace, 'it'))->not->toBe('parties.profile.club_at_capacity')
        ->and(trans('parties.profile.club_at_capacity', $replace, 'it'))->toContain('waiting_list')
        ->and(trans('parties.profile.club_at_capacity', $replace, 'it'))->toContain('88')
        ->and(trans('parties.profile.club_at_capacity', $replace, 'it'))->toContain('77')
        ->and(trans('parties.profile.club_at_capacity', $replace, 'it'))
        ->not->toBe(trans('parties.profile.club_at_capacity', $replace, 'en'));
});

// The capacity key is ADDED alongside the membership / status from-state guards of the same `profile` group — not a
// rewrite. Every pre-existing factory of this class must still build its own localized reason.
it('preserves the pre-existing profile from-state rejections', function () {
    expect(IllegalProfileTransition::cannotApprove(ProfileState::Active)->getMessage())->toContain('active');
    expect(IllegalProfileTransition::cannotRenew(ProfileState::Active)->getMessage())->toContain('active');
    expect(__('parties.profile.duplicate_for_club', ['customer' => 1, 'club' => 2]))
        ->not->toBe('parties.profile.duplicate_for_club');
});
