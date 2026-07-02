<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Parties\Enums\ClubCreditState;
use App\Modules\Parties\Exceptions\ClubCreditIssuancePrecondition;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\ClubCredit;
use App\Modules\Parties\Models\Profile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Creates an `active` Club Credit for a Profile — the SOLE creator of a Club Credit row, AUDIT-ONLY, recording NO
 * domain event (change club-credit, design L1/L2/L3/L4/L5/L8; party-registry — Requirement: Club Credit Issuance;
 * Module K PRD § 11.1; DEC-007 fee → Club Credit).
 *
 * AMOUNT & VALIDITY (design L2; § 11): the credit `amount` equals the owning Club's `fee` VERBATIM — both minor
 * units AND currency — because at launch the welcome-window proportional scaling (K.18) is deferred, so full fee →
 * full credit. `remaining` starts equal to `amount` (an untouched balance — the § 11.2 K.17 carry-forward begins
 * full); `valid_from` is the issuance moment and `valid_to` is 31 December of the issuance year
 * (`CarbonImmutable::now()->endOfYear()` — the PRD default; a per-Club credit-policy override is a deferred seam).
 *
 * TWO ISSUANCE PRECONDITIONS on the Club, each rejected BEFORE any write with a localized
 * {@see ClubCreditIssuancePrecondition} and creating no row (§ 11.1; design L2): the Club must have
 * `generates_credit = true` (a non-credit Club issues nothing — AC-K-J-16), and it must have a non-null `fee` (a
 * `generates_credit = true` Club with no `fee` cannot define an amount — the fee-null guard; no zero/undefined
 * credit is minted). There is NO Profile-state precondition and NO Hold check: issuance is NOT Hold-gated — the
 * entitlement is recorded once the fee is paid; only redemption is Hold-gated (§ 11.2).
 *
 * ONE-ACTIVE INVARIANT — STRUCTURAL (design L1): at most one `active` Club Credit exists per Profile, enforced by
 * the partial unique index `(profile_id) WHERE state = 'active'`, NOT an application-level pre-check (an app-level
 * "find existing and update" loses the guarantee under concurrency — alternative rejected). So issuance runs no
 * one-active query; if an `active` credit already exists for the Profile the insert violates the index and the
 * transaction rolls back. The production renewal listener forfeits-before-issues (`ForfeitClubCredit` then issue —
 * design L5), so it never trips the index in the normal flow; the index is the concurrency backstop, and the
 * forfeit-before-issue ordering it enforces is exercised directly in the forfeiture tests.
 *
 * AUDIT-ONLY (design L3; § 11.4): § 11.4 makes `ClubCreditAccrued` (and the upstream `MembershipFeePaid`) MODULE E's
 * events — Module K consumes them and records the resulting state on its own entity — so, exactly as
 * {@see SuspendAccount} writes `status` and {@see RecordKycVerified} writes `kyc_status` recording NO event, this
 * Action creates the credit and records NO domain event; the entity state is the launch record. It therefore
 * injects NEITHER `DomainEventRecorder` NOR `ActorContext`, and fabricates NO `MembershipFeePaid`/`ClubCreditAccrued`
 * event class (zero-invention).
 *
 * MEMBERSHIP-FEE TRIGGER — DEFERRED MODULE-E SEAM (design L5; § 11.1): in production issuance is driven by Module E's
 * payment-provider-confirmed `MembershipFeePaid` signal. Module E does not exist, so the listener that would invoke
 * this Action is a documented Module-E seam — no Module-E event contract is fabricated. `IssueClubCredit` ships as
 * the within-module writer, invoked by the operator/seam path now and directly in tests — exactly as
 * {@see ActivateProfile} ships its `approved → active` transition with the same fee-paid signal as a seam.
 *
 * K.18 / K.19 RETAINED SEAMS (design L8): the `amount = Club.fee` assignment IS the K.18 welcome-window-scaling hook
 * — when scaling restores, the `policy_amount × (fee_paid / full_fee)` formula slots in at exactly this point
 * (AC-K-MVP-3). No operator manual-create surface is built (K.19 — AC-K-MVP-4): launch goodwill routes through the
 * Module S `REFUND_COMPENSATION` coupon; this writer is itself the retained K.19 manual-issuance seam.
 *
 * Transaction-safe (design L4, mirroring {@see SuspendAccount}): inside ONE {@see DB::transaction} it re-reads the
 * Profile and its Club `->lockForUpdate()` (a real row lock on PostgreSQL, a no-op under SQLite — the precondition
 * asserts carry correctness either way), checks the two preconditions, then creates the credit. The ClubCredit model
 * stays persistence-only (`$guarded = []`); this Action assembles the attributes internally and is the sole creator.
 */
class IssueClubCredit
{
    public function handle(int $profileId): ClubCredit
    {
        return DB::transaction(function () use ($profileId): ClubCredit {
            // Transaction-locked re-read of the Profile and its Club so two concurrent issuance attempts serialize on
            // PostgreSQL; the precondition asserts below carry correctness either way (the lock is a no-op on SQLite).
            $profile = Profile::query()->whereKey($profileId)->lockForUpdate()->firstOrFail();
            $club = Club::query()->whereKey($profile->club_id)->lockForUpdate()->firstOrFail();

            // Precondition 1 (§ 11.1; design L2): issuance is gated on `generates_credit = true`; a non-credit Club
            // issues nothing.
            if (! $club->generates_credit) {
                throw ClubCreditIssuancePrecondition::clubDoesNotGenerateCredit($club->id);
            }

            // Precondition 2 (§ 11.1; design L2 fee-null guard): the credit `amount` IS the fee verbatim, so a Club
            // with `generates_credit = true` but no `fee` cannot define an amount — refuse rather than mint a
            // zero/undefined credit. The non-null narrows `$fee` to Money for the create below.
            $fee = $club->fee;
            if ($fee === null) {
                throw ClubCreditIssuancePrecondition::clubHasNoFee($club->id);
            }

            $now = CarbonImmutable::now();

            // AUDIT-ONLY (design L3; § 11.4): create the `active` credit and record NO domain event. `amount` = the
            // fee verbatim (the immutable Money carries both minor units and currency — design L2); `remaining` =
            // `amount` (the K.17 carry-forward starts full); the validity window is the issuance instant → 31 Dec of
            // the issuance year. The one-active partial index is the structural guard — a re-issue while an `active`
            // credit exists violates it and rolls this transaction back (design L1).
            return ClubCredit::create([
                'profile_id' => $profile->id,
                'amount' => $fee,
                'remaining' => $fee,
                'valid_from' => $now,
                'valid_to' => $now->endOfYear(),
                'state' => ClubCreditState::Active,
            ]);
        });
    }
}
