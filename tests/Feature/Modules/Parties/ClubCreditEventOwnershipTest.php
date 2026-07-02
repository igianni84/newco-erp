<?php

use App\Modules\Parties\Actions\ApplyClubCredit;
use App\Modules\Parties\Actions\ForfeitClubCredit;
use App\Modules\Parties\Actions\IssueClubCredit;
use App\Modules\Parties\Actions\RestoreClubCredit;
use App\Modules\Parties\Enums\ClubCreditState;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Profile;
use App\Platform\Events\DomainEvent;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pins the § 11.4 OWNERSHIP BOUNDARY for Club Credit (change club-credit task 5.1; design L3/L9; party-registry —
 * Requirement: Club Credit State Recording Is Module-E-Owned; Module K PRD § 11.4 / § 15.8). § 11.4 makes
 * `ClubCreditAccrued` (issuance — canon DEC-018 renamed it from `ClubCreditIssued`) / `ClubCreditApplied` /
 * `ClubCreditRestored` / `ClubCreditForfeited` — and the upstream `MembershipFeePaid` — events Module K does NOT
 * emit: it consumes them and records the resulting state on its own entity. (Per DEC-018 the *application* event
 * `ClubCreditApplied` is Module-S-emitted, accrual/restore/forfeit Module-E; the Module-S re-home is a deferred
 * seam — decisions/2026-07-02-adopt-dec-018-clubcredit-accrued.md. Module K's non-emission holds either way.)
 * Because Module E does not exist (Phase 6), the four within-module writers are AUDIT-ONLY (design L3) — they
 * `update()` the credit `state`/`remaining` and emit NO domain event — and NO such event class is fabricated under
 * Parties (zero-invention; design L9 confirmed no shipped forbidden-name list pre-named these, so this is a NEW
 * guard, not a realignment). This file asserts both halves of the boundary:
 *   - NO MODULE-E EVENT CLASS IS FABRICATED (delta scenario "No Module-E event class is fabricated"): none of the
 *     five non-Module-K-owned names exists as an event file under `app/Modules/Parties/Events` — proven by reflecting the
 *     Events namespace off the filesystem (mirroring the `SupplyLifecycleChainTest` event-non-existence loop; the
 *     filesystem glob is the analyzer-safe way to assert a class's ABSENCE — a `class_exists` on a known-absent class
 *     reds PHPStan max as `function.impossibleType`);
 *   - NO CLUB CREDIT DOMAIN EVENT IS EMITTED BY MODULE K (delta scenario "No Club Credit domain event is emitted by
 *     Module K"): the full within-module FSM driven end-to-end through the REAL writers records ZERO `domain_events`
 *     rows. This GENERALIZES the per-writer no-event deltas the slice already proved in isolation (issuance 2.2,
 *     redemption 3.2, forfeiture + restoration 4.3) into one ownership-boundary assertion across all four writers.
 *
 * The walk exercises every writer at least once while respecting the one-active-per-Profile invariant the whole way:
 * Issue → Apply (partial, K.17 carry-forward, stays `active`) → Forfeit (frees the one-active slot) → re-Issue (a
 * fresh `active` credit — the forfeit-before-issue ordering) → Apply (full → `redeemed`) → Restore (`redeemed →
 * active`; the forfeited first credit is outside the `active` scope, so no other active credit blocks it). A single
 * before/after `count()` DELTA of 0 then proves no writer recorded a row — strictly stronger than a by-name check
 * (zero rows of ANY name subsumes zero rows of the five Module-E names), and the assertion that catches a future
 * writer that starts emitting.
 *
 * RefreshDatabase per the directory convention. PostgreSQL 17: test-only, NO DDL — the `glob` reflection is
 * filesystem-only and the `DomainEvent::query()->count()` snapshot is engine-identical; each writer
 * opens its OWN `DB::transaction` (a SAVEPOINT under the wrapper), and no clock-sensitive validity window is asserted
 * (the re-issue sets its window from the live clock but only `state`/identity are observed), so the file holds on
 * both engines with no frozen clock. CI `tests-pgsql` verifies on the human push.
 */
uses(RefreshDatabase::class);

it('fabricates no Module-E Club Credit event class under Parties (§ 11.4 ownership boundary)', function () {
    // Reflect the Events namespace off the filesystem exactly as SupplyLifecycleChainTest does (every Parties event
    // is a flat class file directly under Events/). The directory holds the shipped supply-/demand-side events, so
    // the walk is never vacuous.
    $eventFiles = glob(app_path('Modules/Parties/Events/*.php')) ?: [];
    $events = array_map(static fn (string $file): string => basename($file, '.php'), $eventFiles);
    expect($events)->not->toBeEmpty();   // the walk must have run — never a vacuous pass

    // None of the five non-Module-K-owned Club Credit names is fabricated as an event file under Parties (§ 11.4 /
    // § 15.8). Asserted via the filesystem reflection, NOT `class_exists`: PHPStan max constant-folds a `class_exists`
    // on a compile-time-known-absent class to `function.impossibleType` ("always false") — which is exactly why
    // SupplyLifecycleChainTest pins ABSENT events by glob and reserves `class_exists` for PRESENT ones. The glob is
    // runtime-dynamic (the analyzer cannot know what is on disk), so it stays green while still proving the absence.
    foreach (['MembershipFeePaid', 'ClubCreditAccrued', 'ClubCreditApplied', 'ClubCreditRestored', 'ClubCreditForfeited'] as $forbidden) {
        expect($events)->not->toContain($forbidden);
    }
});

it('records zero domain events across the whole Club Credit FSM — Module K records state only (§ 11.4; design L3)', function () {
    // A credit-generating Club (factory default: generates_credit = true, fee = 25000 EUR) and a Profile on it, so the
    // REAL IssueClubCredit can mint a credit. The factories bypass the Actions and record no event, so the log starts
    // empty; snapshot to assert the DELTA across every writer is exactly 0 (honest even if a fixture ever emitted).
    $club = Club::factory()->create();
    $profile = Profile::factory()->create(['club_id' => $club->id]);

    $before = DomainEvent::query()->count();

    // Drive the full within-module FSM through all four REAL writers on ONE Profile, one-active-respecting throughout.
    // 1. Issue the Profile's one `active` credit.
    $first = app(IssueClubCredit::class)->handle($profile->id);
    expect($first->state)->toBe(ClubCreditState::Active);

    // 2. Partial redemption — K.17 carry-forward keeps it `active` (remaining 25000 − 9000 = 16000).
    app(ApplyClubCredit::class)->handle($first->id, Money::of(9000, Currency::EUR));

    // 3. Forfeit it — `active → forfeited`, freeing the one-active slot for a re-issue.
    app(ForfeitClubCredit::class)->handle($first->id);

    // 4. Re-issue now the slot is free — a FRESH `active` credit (the forfeit-before-issue ordering).
    $second = app(IssueClubCredit::class)->handle($profile->id);

    // 5. Full redemption of the new credit — `remaining` hits zero, `active → redeemed`.
    app(ApplyClubCredit::class)->handle($second->id, $second->remaining);

    // 6. Restore the redeemed credit — `redeemed → active` (the forfeited first credit is outside the `active` scope,
    //    so no other active credit blocks the one-active check).
    $restored = app(RestoreClubCredit::class)->handle($second->id);
    expect($restored->state)->toBe(ClubCreditState::Active);   // the chain ran end-to-end through every writer

    // § 11.4: every writer updated `state`/`remaining` and recorded NO domain event — they inject no
    // DomainEventRecorder, and `ClubCreditAccrued`/`Applied`/`Restored`/`Forfeited` + `MembershipFeePaid` are
    // emitted outside Module K (Module-E, and `ClubCreditApplied` from Module S per DEC-018). A zero delta across
    // the whole FSM is the ownership boundary in one assertion. Delta = 0.
    expect(DomainEvent::query()->count())->toBe($before);
});
