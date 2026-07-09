<?php

namespace App\Modules\Parties\Contracts;

/**
 * The Module-K-owned read-port through which the Hero-Package seat gate obtains a Club's membership capacity
 * (parties-hero-package, design D1; party-registry — Requirement: Hero Package Capacity Is Read from Module A,
 * Never Stored in Module K; ADR 2026-07-09-hero-package-capacity-seat-set-and-waitinglist §9).
 *
 * The capacity number is the Hero-Package Allocation's `qty` and is owned by **Module A** (canon `MVP-DEC-020`:
 * Module A owns the number, Module K owns the invariant — *"a K-owned capacity number would be a drift-prone
 * mirror with no independent meaning"*). Module K therefore stores **no capacity value of any kind**: no column
 * on `parties_clubs`, no capacity table, no read-model (`AC-K-XM-20`, verified by schema inspection). It reads
 * the number through this port and enforces the invariant against it.
 *
 * {@see forClub()} returns the Club's capacity, or **`null` meaning UNCAPPED** — the launch posture. `null` is a
 * first-class answer, not an error: with no capacity configured the seat gate is inert and every seat-consuming
 * transition behaves exactly as it did before the gate existed (a dark launch). Zero, by contrast, is a real
 * capacity: a Club admitting nobody.
 *
 * The port commits to **nothing** about Module A's schema or event payloads — it takes a Club id and returns an
 * int (invariant 10; the {@see CustomerTransactionTotalsReader} precedent, the RM-02 seam this mirrors). Module A
 * is a two-file stub, so at launch the interface is bound in `PartiesServiceProvider` to the config-backed
 * `ConfigHeroPackageCapacityReader`. When Module A lands, **only the adapter is replaced** — with a live read, or
 * with the `AllocationCapacity*`-fed reconciling read-model canon permits (`AC-K-XM-18`). That choice belongs to
 * Module A's own gate, on Module A's own evidence; no Action changes either way.
 *
 * The complementary **seat-occupancy count stays internal to Module K** (`Support\ClubSeatOccupancy`): it is
 * deliberately NOT published as a contract until Module A's capacity-decrease floor or Module S's Hero-Package
 * offer gate exists to consume it — a contract with zero consumers is dead code.
 */
interface HeroPackageCapacityReader
{
    /**
     * The Club's Hero-Package membership capacity — the number of seats the seat-occupying set (`Active` +
     * `Suspended` Profiles) may fill — or `null` when the Club is uncapped.
     */
    public function forClub(int $clubId): ?int;
}
