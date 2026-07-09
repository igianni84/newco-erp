<?php

namespace App\Modules\Parties\Reads;

use App\Modules\Parties\Contracts\HeroPackageCapacityReader;

/**
 * The launch-time config-backed {@see HeroPackageCapacityReader}: it resolves a Club's Hero-Package capacity from
 * `config('parties.hero_package.capacity')` — a per-Club `by_club_id` override, else the global `default`
 * (parties-hero-package, design D1/D2; party-registry — Requirement: Hero Package Capacity Is Read from Module A,
 * Never Stored in Module K). The real reader reads Module A's Hero-Package Allocation `qty`, but Module A is a
 * two-file stub — so this adapter is bound in `PartiesServiceProvider` until Module A ships the real one, holding
 * the seam OPEN with **enforcing** semantics.
 *
 * It is deliberately NOT a null/uncapped adapter: a vacuous gate is worse than no gate — it would ship the shape
 * of the capacity invariant while leaving the oversell defect fully intact (ADR §9, alternative (a)). Config is
 * the smallest source that makes the gate genuinely demonstrable while storing nothing: a `config()` read is not
 * a Module K schema attribute, so `AC-K-XM-20`'s schema inspection stays satisfied.
 *
 * **The cast is load-bearing.** `config/parties.php` sources the default from `env('PARTIES_HERO_PACKAGE_CAPACITY')`,
 * and `env()` yields a **string**; the contract returns `?int`, and the seat gate compares the result to an
 * occupancy count. Both the override and the default therefore pass through {@see toCapacity()}.
 *
 * Two shapes an operator must be able to express, and one they must not:
 * - `null` (absent, or a non-numeric value) ⇒ **uncapped**, the launch posture. A malformed value normalises back
 *   to uncapped rather than to a garbage `(int)` cast — the `Catalog\Lifecycle\ApprovalGovernance::roleCount()`
 *   precedent, which likewise normalises a non-numeric read to its own safe default.
 * - `0` ⇒ a real capacity of zero seats (a Club admitting nobody), never confused with absence.
 * - A `by_club_id` entry wins on **presence, not truthiness** — an explicit `null` there pins one Club uncapped
 *   beneath a capped global default, and must not fall through to it.
 *
 * It reads config and never looks a Club up (the id is a lookup key, not a row), so it performs no database access
 * and references NOTHING under `App\Modules\Allocation` — no Module A model, table or event (invariant 10; pinned
 * by the arch assertion in the binding test). Stateless, so a plain `bind` (fresh per resolve) is enough.
 */
class ConfigHeroPackageCapacityReader implements HeroPackageCapacityReader
{
    public function forClub(int $clubId): ?int
    {
        $byClubId = config('parties.hero_package.capacity.by_club_id');

        if (is_array($byClubId) && array_key_exists($clubId, $byClubId)) {
            return self::toCapacity($byClubId[$clubId]);
        }

        return self::toCapacity(config('parties.hero_package.capacity.default'));
    }

    /** Normalise a raw config read to the contract's `?int`: a numeric value is the capacity, anything else is uncapped. */
    private static function toCapacity(mixed $configured): ?int
    {
        return is_numeric($configured) ? (int) $configured : null;
    }
}
