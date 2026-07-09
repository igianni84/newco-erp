<?php

namespace App\Modules\Parties\Support;

use App\Modules\Parties\Contracts\HeroPackageCapacityReader;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\Profile;

/**
 * ClubSeatOccupancy — the K-internal seat ledger of a Club's Hero Package: it counts the Profiles that OCCUPY a
 * seat, under the Club-row lock that makes the count trustworthy, and answers whether one more seat-consuming
 * transition would breach the Club's capacity (parties-hero-package task 1.2, design D3/D6/D10; party-registry —
 * Requirement: Hero Package Capacity Invariant; § 13 / AC-K-J-13;
 * ADR 2026-07-09-hero-package-capacity-seat-set-and-waitinglist).
 *
 * THE SEAT SET IS `Active` + `Suspended`, AND NOTHING ELSE ({@see OCCUPYING_STATES}). Occupancy is DERIVED from
 * Profile state — there is no seat entity, no seat row, no counter column (`AC-K-XM-20`, verified by schema
 * inspection). Each exclusion is a decision, not an omission:
 *   - `Suspended` OCCUPIES. A suspension is a temporary restriction, not a departure: the seat was never freed
 *     (canon § 13.1, § 10.1). This is exactly why `ReactivateProfile` is NEVER capacity-gated (design D4) —
 *     re-checking on restore would let a temporary Hold EVICT a member (AC-K-FSM-2a).
 *   - `Applied` and `WaitingList` do NOT occupy. Neither holds a seat, which is precisely why the birth-state gate
 *     in `CreateProfile` cannot oversell and therefore takes no Club-row lock (design D6): it routes an applicant,
 *     it does not consume a seat.
 *   - `Approved` does NOT occupy — sound ONLY because `Approved` is a TRANSIENT pass-through, never durably
 *     rested-in (§ 4.2.1 / AC-K-FSM-2): `ApproveProfile` evaluates the gate BEFORE it writes `approved` and drives
 *     straight through to `active` inside the same transaction. Counting it would count the same seat twice — the
 *     reason the gate lives on the seat-consuming caller and not on `ActivateProfile` (design D4).
 *   - `Rejected`, `Lapsed`, `Cancelled`, `Inactive` do NOT occupy: the membership is over. The freed seat is never
 *     auto-filled — no listener, scheduler, job or observer promotes a waitlisted Profile (design D5; canon
 *     MVP-DEC-011: *shrink by attrition, no backfill*; § 13.5: no automatic FIFO conversion at launch).
 *
 * THE LOCK IS ORDERED STRICTLY BEFORE THE COUNT, AND THAT ORDERING IS THE FIX (design D3). Locking the Profile row
 * — as `ApproveProfile` did before this change — serialises nothing that matters here: two concurrent approvals of
 * DIFFERENT Profiles in the SAME Club lock different rows, both read `49/50`, both pass the gate, and the Club ends
 * with 51 occupied seats against a capacity of 50. Serialising on the `parties_clubs` row instead makes same-Club
 * seat-consuming transactions queue while leaving different Clubs fully parallel. Hence
 * {@see lockAndCountOccupiedSeats()}, which acquires the lock and only then counts, is the ONLY entry point a
 * seat-consuming caller may use — and it MUST be called inside an already-open `DB::transaction`, or the lock is
 * released the instant the implicit transaction ends and the count means nothing.
 *
 * ENGINE ASYMMETRY: PostgreSQL emits `SELECT … FOR UPDATE` and genuinely serialises. SQLite's grammar compiles NO
 * lock clause at all, so on the dev/test engine the lock is a no-op and the serialisation claim is not provable
 * there. The concurrency proof therefore lives in the PostgreSQL 17 lane only (task 3.2); on SQLite the sequential
 * gate is what the suite pins.
 *
 * A K-INTERNAL HELPER, deliberately not published. It is NOT a `Contracts/` port (that folder is Module K's
 * cross-module public surface, and a contract with zero consumers is dead code — the ADR's ruling: the count stays
 * internal until Module A's capacity-decrease floor or Module S's Hero-Package offer gate exists to consume it),
 * NOT bound in the container (it is autowired), and NOT under `Actions/` (design D10 — `SupplyLifecycleChainTest`
 * pins the exact non-`Create*` file set of that directory, and the capacity gate needs no Action of its own:
 * conversion is `ApproveProfile` from `waiting_list`, decline-from-waitlist is `DeclineProfile`, the renewal gate is
 * inside `RenewProfile`). It reads the capacity through Module K's own {@see HeroPackageCapacityReader} port and so
 * references nothing under `App\Modules\Allocation` (invariant 10).
 */
class ClubSeatOccupancy
{
    /**
     * The seat-occupying Profile states — the ONE place this change defines what "a seat" means. Every other
     * consumer derives from it, so the seat set can never be spelled two ways.
     *
     * @var list<ProfileState>
     */
    public const OCCUPYING_STATES = [ProfileState::Active, ProfileState::Suspended];

    public function __construct(private readonly HeroPackageCapacityReader $capacity) {}

    /**
     * Acquire the `parties_clubs` row's FOR UPDATE lock for this transaction, THEN count the Club's occupied
     * seats. The ordering is the whole point (design D3): every seat-consuming transition — approve, waitlist
     * conversion, renewal — reads its occupancy through here, so same-Club transactions serialise on that one row
     * and no two of them can both observe the last free seat.
     *
     * MUST be called inside an already-open `DB::transaction` — outside one, PostgreSQL releases the lock as the
     * implicit transaction commits and the count is stale before the caller reads it. A no-op under SQLite, which
     * compiles no lock clause (see the class docblock's engine-asymmetry note).
     *
     * Throws `ModelNotFoundException` when the Club does not exist: a seat-consuming transition against an unknown
     * Club is a programming error, and failing loudly beats gating against a phantom occupancy of zero.
     */
    public function lockAndCountOccupiedSeats(int $clubId): int
    {
        Club::query()->whereKey($clubId)->lockForUpdate()->firstOrFail();

        return $this->countOccupiedSeats($clubId);
    }

    /**
     * The lock-free occupancy count. Reserved for reads that STRUCTURALLY cannot oversell — today exactly one:
     * `CreateProfile`'s birth-state routing gate (design D6), whose two outcomes are `Applied` and `WaitingList`
     * and neither holds a seat. A Profile born `applied` into a Club that reaches parity a microsecond later is
     * harmless: the approve gate intercepts it. Locking there would serialise every application in a Club for no
     * invariant gain.
     *
     * Every SEAT-CONSUMING caller must use {@see lockAndCountOccupiedSeats()} instead.
     *
     * Unlike its locking sibling this performs no Club lookup, so an unknown Club id simply counts zero.
     */
    public function countOccupiedSeats(int $clubId): int
    {
        return Profile::query()
            ->where('club_id', $clubId)
            ->whereIn('state', array_map(
                fn (ProfileState $state): string => $state->value,
                self::OCCUPYING_STATES,
            ))
            ->count();
    }

    /**
     * Would ONE more seat-consuming transition breach the Club's capacity? A pure comparison over the capacity
     * port — it performs NO database access, which is what lets `CreateProfile` consult it without a lock.
     *
     * The caller supplies the occupancy it counted, and owns the choice of how: under the Club-row lock when the
     * transition consumes a seat ({@see lockAndCountOccupiedSeats()}), lock-free when it cannot
     * ({@see countOccupiedSeats()}). Handing the number back in also lets the caller reuse it in the operator-facing
     * rejection reason without counting twice.
     *
     * `null` capacity means UNCAPPED — never an oversell, at any occupancy. That is the shipped production posture
     * (a dark launch), and the reason every pre-existing Parties test runs against unchanged behaviour. `0`, by
     * contrast, is a real capacity: a Club admitting nobody oversells on its very first seat.
     *
     * The comparison is `>=`, not `>`: an occupancy already equal to capacity has no free seat, and an occupancy
     * ABOVE it (a capacity lowered beneath the sitting members) must not admit anyone either.
     */
    public function wouldOversell(int $clubId, int $occupiedSeats): bool
    {
        $capacity = $this->capacity->forClub($clubId);

        return $capacity !== null && $occupiedSeats >= $capacity;
    }
}
